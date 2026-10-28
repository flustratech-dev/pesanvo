import assert from 'node:assert/strict';
import test from 'node:test';
import process from 'node:process';

/**
 * Sejak engine berbagi container dengan Laravel (../../start.sh), tiga nama
 * variabel bertabrakan: PORT, HOST, dan LOG_LEVEL sudah dipakai Laravel dan
 * Coolify. Engine memakai versi berawalan ENGINE_.
 *
 * Kenapa ini diuji: kegagalannya diam. Engine yang salah membaca LOG_LEVEL=error
 * milik Laravel tetap berjalan normal — yang hilang cuma baris "Backup sesi
 * terkirim", satu-satunya bukti bahwa cadangan sesi benar-benar tersimpan, dan
 * itulah patokan Uji 1 di docs/DEPLOYMENT.md. Engine yang salah membaca PORT
 * milik Coolify akan mendengarkan di port yang salah dan setiap permintaan
 * Laravel berakhir "connection refused" tanpa petunjuk apa pun.
 */

const wajib = {
    ENGINE_TOKEN: 'token-uji',
    ENGINE_HMAC_SECRET: 'secret-uji',
    LARAVEL_URL: 'http://127.0.0.1:80',
};

let penghitung = 0;

async function muat(env) {
    const asli = { ...process.env };

    for (const kunci of ['PORT', 'HOST', 'LOG_LEVEL', 'ENGINE_PORT', 'ENGINE_HOST', 'ENGINE_LOG_LEVEL', 'WA_MAX_SESSIONS']) {
        delete process.env[kunci];
    }

    Object.assign(process.env, wajib, env);

    // Query string dipakai untuk melewati cache modul — config.js dievaluasi
    // sekali saat diimpor, jadi tiap kasus uji butuh salinannya sendiri.
    const { config } = await import(`../src/config.js?uji=${penghitung++}`);

    process.env = asli;

    return config;
}

test('ENGINE_PORT dan ENGINE_HOST menang atas PORT dan HOST milik Laravel', async () => {
    const config = await muat({
        PORT: '80',
        HOST: '0.0.0.0',
        ENGINE_PORT: '3100',
        ENGINE_HOST: '127.0.0.1',
    });

    assert.equal(config.port, 3100);
    assert.equal(config.host, '127.0.0.1');
});

test('PORT dan HOST tetap dipakai kalau versi ENGINE_ tidak ada', async () => {
    const config = await muat({ PORT: '3999', HOST: '0.0.0.0' });

    assert.equal(config.port, 3999);
    assert.equal(config.host, '0.0.0.0');
});

test('ENGINE_LOG_LEVEL menang atas LOG_LEVEL milik Laravel', async () => {
    const config = await muat({ LOG_LEVEL: 'error', ENGINE_LOG_LEVEL: 'info' });

    assert.equal(config.logLevel, 'info');
});

test('variabel kosong diperlakukan sebagai tidak diisi, bukan sebagai nol', async () => {
    // Coolify mengirim variabel yang dikosongkan sebagai string kosong, dan
    // Number('') menghasilkan 0. WA_MAX_SESSIONS=0 berarti tidak ada satu pun
    // sesi yang boleh berjalan — gateway diam total dengan pesan galat yang
    // menyebut batas yang tidak pernah disetel siapa pun.
    const config = await muat({ WA_MAX_SESSIONS: '' });

    assert.equal(config.maxSessions, 3);
});

