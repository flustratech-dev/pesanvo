# Panduan Kontribusi

Aturan kerja di repo ini: alur git, standar kode, dan daftar periksa sebelum menggabungkan perubahan.

---

## Alur git

| Branch | Tahap | Domain | Deploy |
|---|---|---|---|
| `dev` | Development | `wa-dev.pesanvo.id` | Otomatis setiap push |
| `staging` | Staging | `wa-staging.pesanvo.id` | Otomatis setiap push |
| `main` | Production | `wa.pesanvo.id` | Otomatis setiap push |

Arahnya selalu satu: `dev` → `staging` → `main`. Jangan pernah mendorong langsung ke `main`.

```bash
git checkout dev
git pull
# … kerjakan …
php artisan test
git commit
git push origin dev
```

Setelah teruji di dev:

```bash
git checkout staging && git merge dev && git push origin staging
```

Setelah teruji di staging:

```bash
git checkout main && git merge staging && git push origin main
```

Untuk perubahan besar, kerjakan di branch fitur dari `dev`, lalu buka pull request ke `dev`.

## Pesan commit

Bahasa Indonesia, baris pertama menjelaskan **apa yang berubah dari sudut pandang sistem**, bukan file mana yang disentuh.

```
Perbaiki tabrakan nama rute antara REST API dan dashboard

Route::apiResource mendaftarkan nama sessions.index yang sama dengan rute
dashboard. Karena rute API dimuat belakangan, route('sessions.index')
menghasilkan URL API dan pengguna yang baru mendaftar dilempar ke JSON.

Nama rute API sekarang berawalan `api.`, plus tes yang memeriksa path
harfiahnya supaya tabrakan serupa langsung ketahuan.
```

Badan commit menjelaskan **kenapa**, dan kalau perbaikan bug, **bagaimana gejalanya**. Itu yang berguna saat seseorang membaca `git log` enam bulan lagi.

## Standar kode

### PHP

Mengikuti Laravel Pint. Jalankan sebelum commit:

```bash
./vendor/bin/pint
```

- Type hint di semua parameter dan nilai balik
- Constructor property promotion untuk dependensi
- `match` daripada rantai `if/elseif` untuk pemetaan nilai
- Kelas `final` tidak dipakai — kelas di sini memang dimaksudkan bisa diperluas

### JavaScript

- ESM (`import`, bukan `require`)
- `const` sebagai default
- `async/await` daripada rantai `.then()`
- Private class field (`#nama`) untuk keadaan internal

### Bahasa

**Semua teks dalam Bahasa Indonesia**: komentar, pesan galat, nama tes, dokumentasi, dan seluruh antarmuka.

Istilah teknis tanpa padanan mapan boleh tetap bahasa Inggris: queue, webhook, endpoint, timestamp, cache, request, response.

Nama kelas, method, variabel, dan kolom database tetap bahasa Inggris — mengikuti konvensi Laravel dan ekosistem Pesanvo lainnya.

### Komentar

Komentar menjelaskan **alasan**, bukan mekanisme. Kode sudah menunjukkan apa yang terjadi.

```php
// Buruk — mengulang yang sudah jelas dari kodenya
// Naikkan hitungan pemakaian
$this->incrementUsage($workspace, 'messages_sent');

// Baik — menjelaskan kenapa di titik ini, bukan nanti
// Kuota dinaikkan saat antre, bukan saat terkirim: kalau dihitung
// belakangan, satu workspace bisa mengantrekan puluhan ribu pesan dulu
// lalu baru ketahuan melewati batas.
$this->incrementUsage($workspace, 'messages_sent');
```

Komentar paling berharga adalah yang menjawab "kenapa tidak dengan cara yang lebih sederhana?" — karena itu pertanyaan yang pasti muncul di kepala pembaca berikutnya.

## Tes

Wajib untuk: endpoint API baru, event engine baru, perubahan logika kuota atau isolasi workspace, dan setiap perbaikan bug.

Nama method dalam Bahasa Indonesia, mendeskripsikan perilaku yang dijaga:

```php
public function test_kuota_habis_menolak_pesan_berikutnya(): void
public function test_status_pesan_tidak_pernah_mundur(): void
public function test_kunci_workspace_lain_tidak_bisa_melihat_sesi_kita(): void
```

Sertakan docblock yang menjelaskan kenapa perilaku itu penting:

```php
/**
 * Ack dari WhatsApp bisa datang tidak berurutan. Tanpa penjagaan, pesan
 * yang sudah "read" bisa turun lagi jadi "delivered".
 */
```

**Setiap perbaikan bug disertai tes yang gagal sebelum perbaikan.** Tanpa itu, tidak ada yang mencegah bug yang sama kembali.

```bash
php artisan test
php artisan test --filter=SendMessageTest
```

## Perubahan skema

Setelah rilis pertama: **selalu migrasi baru**, jangan pernah mengubah migrasi yang sudah dijalankan di produksi.

Perbarui juga:
1. `$fillable` dan `casts()` di model
2. [REFERENSI_DATABASE.md](REFERENSI_DATABASE.md) — termasuk alasan kolomnya ada
3. Tes yang menyentuhnya

Hindari kolom yang tidak dipakai apa pun. Kolom mati membingungkan pembaca berikutnya, yang akan menghabiskan waktu mencari siapa yang menulisnya.

## Daftar periksa sebelum menggabungkan

- [ ] `php artisan test` hijau
- [ ] `./vendor/bin/pint` sudah dijalankan
- [ ] Tidak ada `dd()`, `dump()`, `console.log` yang tertinggal
- [ ] Tidak ada rahasia di kode — semua lewat env
- [ ] Nomor telepon tidak ditulis utuh ke log
- [ ] Kueri baru berangkat dari workspace, bukan model global
- [ ] Env baru didaftarkan di [ENVIRONMENT.md](ENVIRONMENT.md) **dan** diisikan di Coolify untuk ketiga tahap — tidak ada template di repo yang akan mengingatkanmu kalau terlewat
- [ ] Endpoint API baru terdokumentasi di [API.md](API.md)
- [ ] Perubahan perilaku terdokumentasi di dokumen yang relevan

## Yang tidak boleh di-commit

`.env`, `engine/.env`, isi `.wwebjs_auth/`, file zip sesi, database SQLite, `vendor/`, `node_modules/`.

Semuanya sudah masuk `.gitignore`. **Kredensial WhatsApp yang bocor sama saja memberi orang lain akses penuh ke nomor tersebut** — termasuk membaca seluruh riwayat chat pribadi pemiliknya.

Sebelum commit pertama di mesin baru, periksa:

```bash
git add -A
git diff --cached --name-only | grep -E '(^|/)\.env$|wwebjs_auth|\.sqlite$'
# harus tidak ada keluaran
```

## Menambah env baru

Env baru harus ditambahkan ke **empat** file template sekaligus, dengan nilai yang sesuai tiap tahap:

| File | Nilai |
|---|---|
| `.env` lokal | Nilai lokal |
| Coolify `pesanvo-dev` | Boleh longgar |
| Coolify `pesanvo-staging` | Sama dengan production |
| Coolify `pesanvo` | Nilai konservatif |

Lalu perbarui berkas `.env.<tahap>` di root repo ini, dan `docs/ENVIRONMENT.md` bila nilainya berbeda antar tahap.

Env yang hanya ditambahkan di satu file akan terlewat saat deploy ke tahap lain, dan gejalanya muncul jauh dari penyebabnya.

## Memperbarui whatsapp-web.js

Pekerjaan rutin per kuartal — versi lama bisa berhenti bekerja saat WhatsApp Web berubah.

```bash
npm --prefix engine update whatsapp-web.js
```

Setelah itu periksa `#bindEvents` di `session-manager.js` masih cocok dengan nama event versi baru, lalu uji berurutan di development dan staging sebelum produksi.

## Kalau ragu

- Struktur kode → [REFERENSI_KODE.md](REFERENSI_KODE.md)
- Kolom database → [REFERENSI_DATABASE.md](REFERENSI_DATABASE.md)
- Kenapa sesuatu dirancang begitu → [ARSITEKTUR.md](ARSITEKTUR.md)
- Sisi engine → [ENGINE.md](ENGINE.md)

Kalau jawabannya tidak ada di sana, itu tanda dokumentasinya yang perlu ditambah.
