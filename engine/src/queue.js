import { config } from './config.js';
import { logger } from './logger.js';

/**
 * Antrean pesan keluar per sesi.
 *
 * Dua alasan antrean ini ada terpisah dari antrean Laravel:
 *
 * 1. Isolasi. Tiap sesi punya antreannya sendiri, jadi broadcast 500 pesan
 *    milik satu tenant tidak menahan satu pesan mendesak milik tenant lain.
 *
 * 2. Jeda anti-ban. WhatsApp memblokir nomor yang mengirim beruntun tanpa jeda
 *    seperti mesin. Jeda acak antara min dan max membuat pola pengirimannya
 *    tidak seragam.
 */
export class SendQueue {
    #queues = new Map();

    /**
     * @param {string} sessionId
     * @param {() => Promise<any>} task
     * @returns {Promise<any>} hasil task, setelah gilirannya tiba
     */
    enqueue(sessionId, task) {
        const previous = this.#queues.get(sessionId) ?? Promise.resolve();

        const current = previous
            .catch(() => {})
            .then(async () => {
                await this.#delay();

                return task();
            });

        // Rantai disimpan tanpa hasilnya supaya kegagalan satu pesan tidak
        // membuat pesan berikutnya di sesi yang sama ikut ditolak.
        this.#queues.set(
            sessionId,
            current.catch(() => {})
        );

        return current;
    }

    depth(sessionId) {
        return this.#queues.has(sessionId) ? 1 : 0;
    }

    forget(sessionId) {
        this.#queues.delete(sessionId);
    }

    async #delay() {
        const { minDelayMs, maxDelayMs } = config;
        const ms = minDelayMs + Math.floor(Math.random() * Math.max(0, maxDelayMs - minDelayMs));

        logger.debug({ ms }, 'Menunggu jeda antar pesan');

        await new Promise((resolve) => setTimeout(resolve, ms));
    }
}
