# Pesanvo Gateway

Gateway WhatsApp terpusat untuk ekosistem Pesanvo — multi-nomor, multi-tenant, REST API, webhook, dan riwayat pesan. Dirancang agar bisa dijual sebagai SaaS.

**Satu kalimat:** aplikasi Anda memanggil satu endpoint HTTP, dan pesan WhatsApp terkirim dari nomor yang sudah Anda tautkan.

```bash
curl -X POST https://wa.pesanvo.id/api/v1/messages/text \
  -H "X-Api-Key: vwa_a1b2c3d4.…" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Invoice INV-001 sudah lunas."}'
```

---

## Dokumentasi

Ada **dua kumpulan yang terpisah**, dan penting untuk tidak tertukar:

| | Untuk siapa | Lokasi | Tampil di web? |
|---|---|---|---|
| **Internal** | Tim pengembang | `docs/` | Tidak |
| **Produk** | Pelanggan & developer yang memanggil API | `resources/docs/` | Ya, di `/docs` |

Dokumentasi produk berisi cara memakai gateway dan contoh kode integrasi. Cara memasang source code, arsitektur, dan seluk-beluk internal **tidak pernah** muncul di sana — ada tes yang menjaganya (`DocsTest`).

Peta dokumentasi internal ada di **[docs/README.md](docs/README.md)**. Yang paling sering dibuka:

| Dokumen | Isi |
|---|---|
| **[docs/PENGANTAR.md](docs/PENGANTAR.md)** | **Mulai di sini.** Apa itu, untuk apa, masalah yang diselesaikan, istilah |
| [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md) | Cara bagian-bagiannya bekerja sama, alur data, alasan tiap keputusan |
| [docs/PANDUAN_DEVELOPER.md](docs/PANDUAN_DEVELOPER.md) | Menyiapkan mesin, struktur kode, menambah fitur, menulis tes |
| [docs/REFERENSI_KODE.md](docs/REFERENSI_KODE.md) | Setiap kelas penting: tanggung jawab & jebakannya |
| [docs/REFERENSI_DATABASE.md](docs/REFERENSI_DATABASE.md) | Setiap tabel & kolom, dan alasan keberadaannya |
| [docs/ENGINE.md](docs/ENGINE.md) | Seluk-beluk engine Node.js |
| [docs/API.md](docs/API.md) | Referensi REST API v1 & webhook, dengan contoh |
| [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md) | Tiga tahap: development, staging, production |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deploy di Coolify, langkah demi langkah |
| [docs/OPERASIONAL.md](docs/OPERASIONAL.md) | Pemantauan, pemulihan, pemecahan masalah |
| [docs/KONTRIBUSI.md](docs/KONTRIBUSI.md) | Alur git, standar kode, daftar periksa |
| [docs/FAQ.md](docs/FAQ.md) &middot; [docs/GLOSARIUM.md](docs/GLOSARIUM.md) | Pertanyaan umum & istilah |

## Ringkasan teknis

- **Laravel 12** (PHP 8.2+) — dashboard, REST API, workspace, antrean, webhook
- **Node.js 20+** — engine `whatsapp-web.js` yang menjalankan Chromium
- **MySQL** di produksi, SQLite untuk lokal
- **Blade + Tailwind v4 + Alpine.js** — tanpa SPA

Satu repo, tiga deployable: root = Laravel, `engine/` = Node, plus satu worker antrean.

## Menjalankan lokal

```bash
composer install
npm install
npm --prefix engine install
```

Buat `.env` di root **dan** di `engine/` — daftar variabelnya ada di [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md). Isi `ENGINE_TOKEN` dan `ENGINE_HMAC_SECRET` dengan nilai acak yang **sama persis** di keduanya:

> Nilai env untuk server tidak disimpan di repo. Acuannya ada di halaman Environment Variables tiap resource Coolify; daftar variabel dan artinya di [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md).

```bash
php artisan key:generate
php artisan migrate
```

Jalankan semuanya sekaligus:

```bash
npm run all
```

- Laravel — http://localhost:8051
- Engine — http://127.0.0.1:3100
- Vite dan queue worker ikut berjalan

Daftar akun lewat http://localhost:8051/register, lalu buat sesi dan scan QR-nya.

Detail lengkap ada di [docs/PANDUAN_DEVELOPER.md](docs/PANDUAN_DEVELOPER.md).

## Tes

```bash
php artisan test
```

## Lisensi

Proprietary — milik PT NAMA PERUSAHAAN (Pesanvo). Bukan produk resmi WhatsApp atau Meta.
