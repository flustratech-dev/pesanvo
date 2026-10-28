import { createHmac } from 'node:crypto';
import { config } from './config.js';
import { logger } from './logger.js';

/**
 * Klien HTTP ke endpoint /internal/* milik Laravel.
 *
 * Setiap request ditandatangani HMAC atas timestamp + isi body. Timestamp ikut
 * ditandatangani supaya callback lama yang tertangkap pihak lain tidak bisa
 * diputar ulang — Laravel menolak tanda tangan yang lebih tua dari 5 menit.
 */
function sign(payload) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const signature = createHmac('sha256', config.hmacSecret)
        .update(`${timestamp}.${payload}`)
        .digest('hex');

    return { timestamp, signature };
}

/**
 * Varian untuk unggahan besar: yang ditandatangani adalah path + sha256 isi
 * file, bukan isinya. Dengan begitu Laravel bisa memverifikasi tanda tangan
 * lebih dulu lalu menulis file secara streaming, tanpa menampung puluhan MB
 * di memori. Keutuhan isi tetap terjaga karena hash-nya dicocokkan ulang.
 */
function signDigest(path, digest) {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const signature = createHmac('sha256', config.hmacSecret)
        .update(`${timestamp}.${path}.${digest}`)
        .digest('hex');

    return { timestamp, signature };
}

async function request(method, path, { body, digest, raw, headers = {} } = {}) {
    const url = `${config.laravelUrl}/${path.replace(/^\/+/, '')}`;
    const payload = raw ?? (body === undefined ? '' : JSON.stringify(body));

    const { timestamp, signature } = digest
        ? signDigest(path.replace(/^\/+/, ''), digest)
        : sign(typeof payload === 'string' ? payload : '');

    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Engine-Timestamp': timestamp,
            'X-Engine-Signature': signature,
            ...(digest ? { 'X-Engine-Body-Sha256': digest } : {}),
            ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: method === 'GET' ? undefined : payload,
        duplex: raw ? 'half' : undefined,
        signal: AbortSignal.timeout(8000),
    });

    return res;
}

export const laravel = {
    /**
     * Daftar sesi yang harus dijalankan ulang saat engine boot. Engine tidak
     * menyimpan daftar ini sendiri, sehingga containernya sepenuhnya bisa
     * dibuang dan dibangun ulang.
     */
    async bootstrap() {
        const res = await request('GET', '/internal/engine/bootstrap');

        if (!res.ok) {
            throw new Error(`Bootstrap gagal: HTTP ${res.status}`);
        }

        const json = await res.json();

        return json.data ?? [];
    },

    async event(sessionId, event, payload = {}) {
        try {
            const res = await request('POST', '/internal/engine/events', {
                body: { session_id: sessionId, event, payload },
            });

            if (!res.ok) {
                logger.warn({ sessionId, event, status: res.status }, 'Laravel menolak event');

                return null;
            }

            return await res.json();
        } catch (error) {
            // Laravel sedang restart bukan alasan untuk menjatuhkan sesi
            // WhatsApp yang sedang sehat — event ini boleh hilang.
            logger.warn({ sessionId, event, err: error.message }, 'Gagal mengirim event ke Laravel');

            return null;
        }
    },

    async backupExists(sessionId) {
        const res = await request('GET', `/internal/engine/session-backup/${sessionId}/exists`);

        // Endpoint-nya selalu menjawab 200 dengan exists true/false, termasuk
        // untuk sesi yang memang belum punya backup. Jadi status selain 200
        // berarti gangguan, bukan "tidak ada" — dan membedakan keduanya penting:
        // pemanggilnya memakai jawaban ini untuk memutuskan apakah kredensial
        // sesi di volume boleh dihapus.
        if (!res.ok) {
            throw new Error(`Gagal memeriksa backup sesi: HTTP ${res.status}`);
        }

        const json = await res.json();

        return json.data?.exists === true;
    },

    async uploadBackup(sessionId, buffer, digest) {
        const res = await request('POST', `/internal/engine/session-backup/${sessionId}`, {
            raw: buffer,
            digest,
            headers: { 'Content-Type': 'application/json' },
        });

        if (!res.ok) {
            throw new Error(`Upload backup gagal: HTTP ${res.status}`);
        }
    },

    async downloadBackup(sessionId) {
        const res = await request('GET', `/internal/engine/session-backup/${sessionId}`);

        if (!res.ok) {
            throw new Error(`Download backup gagal: HTTP ${res.status}`);
        }

        return Buffer.from(await res.arrayBuffer());
    },

    async deleteBackup(sessionId) {
        await request('DELETE', `/internal/engine/session-backup/${sessionId}`);
    },
};
