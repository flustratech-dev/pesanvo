import pino from 'pino';
import { config } from './config.js';

export const logger = pino({
    level: config.logLevel,
    // Nomor telepon tidak boleh utuh di log: log dikirim ke agregator dan
    // disimpan lama, sementara isinya data pribadi pelanggan tenant.
    redact: {
        paths: ['to', 'from', '*.to', '*.from', '*.phone_number'],
        censor: (value) =>
            typeof value === 'string' && value.length > 8
                ? `${value.slice(0, 4)}****${value.slice(-4)}`
                : '****',
    },
});
