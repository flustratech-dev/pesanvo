# Akun tertaut: WA Gateway ↔ pesanvo

Satu akun berlaku di dua aplikasi — `wa.pesanvo.id` dan
`pesanvo.id`. Daftar di salah satunya, akunnya ikut ada di yang lain.
Ganti kata sandi di satu tempat, kata sandi di tempat lain ikut berganti.

**Hanya autentikasi.** Yang dibagi: email, hash kata sandi, dan status
verifikasi email (plus nama dan nomor, tapi hanya untuk mengisi akun yang baru
dibuat). Yang **tidak** dibagi: workspace, tagihan, VPS, organisasi, 2FA, peran
admin. Login tetap di halaman masing-masing aplikasi; tampilannya tidak berubah.

Kode ini ada di **dua repo** dan harus sama persis protokolnya. Mengubah satu
sisi tanpa sisi lain berarti akun berhenti tertaut tanpa satu pun galat yang
terlihat pengguna.

| | pesanvo | pesanvo |
|---|---|---|
| Pengirim | `App\Services\LinkedAccounts\LinkedAccountSync` | sama |
| Penerima | `App\Services\LinkedAccounts\LinkedAccountReceiver` | sama |
| Endpoint | `POST /api/internal/akun-tertaut` | sama |
| Pemicu | `App\Observers\LinkedAccountObserver` pada model `User` | sama |
| Percobaan pertama | job di antrean (`afterCommit`) | job **setelah respons** — worker antrean di pesanvo opsional |
| Pengulangan | `akun-tertaut:kirim` tiap menit | sama |
| Penautan awal | `akun-tertaut:tautkan` | sama |
| Menjemput saat masuk | `LinkedAccountLookup` (login email & Google) | `POST /api/internal/akun-tertaut/cari` |
| Tes | `tests/Feature/AkunTertautTest.php` | sama |

## Env

```env
LINKED_ACCOUNTS_URL=https://pesanvo.id     # di WA: alamat pesanvo; di pesanvo: alamat WA
LINKED_ACCOUNTS_SECRET=<64 heksa>                   # IDENTIK di kedua aplikasi, BERBEDA tiap tahap
```

| Tahap | Di WA Gateway | Di pesanvo |
|---|---|---|
| dev | `https://dev.pesanvo.id` | `https://wa-dev.pesanvo.id` |
| staging | `https://staging.pesanvo.id` | `https://wa-staging.pesanvo.id` |
| produksi | `https://pesanvo.id` | `https://wa.pesanvo.id` |

Salah satunya kosong = penautan **mati di kedua arah**: yang keluar tidak
dikirim, yang masuk dijawab 503. Mati total lebih aman daripada endpoint yang
bisa mengganti kata sandi siapa pun tanpa tanda tangan. Di lokal sengaja
kosong, dan `phpunit.xml` memaksanya kosong supaya tes tidak pernah memanggil
aplikasi seberang.

## Protokol

```
POST /api/internal/akun-tertaut
X-Akun-Timestamp: <unix detik>
X-Akun-Signature: hex(HMAC-SHA256("<timestamp>.<body mentah>", LINKED_ACCOUNTS_SECRET))
Content-Type: application/json

{
  "mode": "sync" | "link",
  "origin": "wa" | "pesanvo",
  "email": "budi@contoh.id",
  "previous_email": null | "budi.lama@contoh.id",
  "name": "Budi Santoso",
  "phone": "6281234567890",
  "password_hash": "$2y$12$…",
  "email_verified_at": null | "2026-09-19T10:00:00+07:00"
}
```

Timestamp yang lebih dari 5 menit dari jam penerima ditolak — menangkap satu
kiriman tidak cukup untuk memutarnya ulang besok.

| Jawaban | Arti | Pengirim |
|---|---|---|
| `200 {"action":"dibuat"}` | akun belum ada, dibuatkan | lepas penanda |
| `200 {"action":"diperbarui"}` | email/kata sandi/verifikasi diganti | lepas penanda |
| `200 {"action":"tidak_berubah"}` | sudah sama | lepas penanda |
| `200 {"action":"dilindungi"}` | akun admin di penerima, atau akun yang sudah ada sementara email kiriman belum terverifikasi — tidak disentuh | lepas penanda |
| `401` | tanda tangan salah/kedaluwarsa — rahasia belum disamakan | **penanda tetap**, diulang |
| `429` | terlalu banyak kiriman | **penanda tetap**, diulang |
| `409` | email baru sudah dipakai akun lain di penerima | lepas penanda, catat `error` |
| `422` | isi tidak sah (mis. `password_hash` bukan hash) | lepas penanda, catat `error` |
| `5xx` / tidak terjangkau | penerima sedang bermasalah | **penanda tetap**, diulang tiap menit |

4xx selain 401 dan 429 tidak diulang: jawabannya tidak akan berubah, dan
mengulangnya tiap menit cuma memenuhi log. 401 dan 429 soal konfigurasi atau
beban, bukan soal akunnya — melepas penanda di situ berarti satu salah ketik
rahasia menghapus seluruh perubahan yang menunggu.

Endpoint dibatasi 600 permintaan per menit — cukup longgar untuk
`akun-tertaut:tautkan` yang mengirim seluruh akun berturut-turut.

## Aturan penerima

1. Cari akun lewat `previous_email` (kalau ada), lalu lewat `email`.
2. Tidak ketemu → **buat**. Di pesanvo `username` dibentuk dari bagian depan
   email (diberi akhiran acak kalau terpakai), `channel = website`; organisasi
   pribadinya dibuatkan saat ia pertama kali masuk, sama seperti pendaftar biasa.
   Di WA, pembebasan tagihan yang disiapkan admin untuk email itu ikut berlaku.
3. Ketemu dan akun itu **admin** di penerima (`is_super_admin` di WA,
   `is_admin` di pesanvo) → tidak diubah sama sekali. Rahasia penautan yang
   bocor di satu aplikasi tidak boleh jadi kunci masuk panel admin aplikasi
   lain; identitas admin keduanya sudah disamakan lewat env `ADMIN_*`.
4. Ketemu, tapi `email_verified_at` di kiriman kosong → **tidak diubah**
   (`dilindungi`). Tanpa aturan ini siapa pun bisa mendaftar di satu aplikasi
   memakai email orang lain — pendaftaran WA tidak memverifikasi email — lalu
   penautan mengganti kata sandi akun asli pemilik email itu di aplikasi lain.
   Akun baru (langkah 2) tetap dibuat tanpa syarat ini: belum ada pemilik yang
   bisa dirugikan. Karena itu verifikasi email ikut memicu pengiriman.
5. Ketemu → ganti email kalau berbeda (di WA `workspaces.owner_email` ikut),
   ganti kata sandi **hanya pada mode `sync`**, isi `email_verified_at` kalau
   di penerima masih kosong. Nama, nomor, dan data profil lain tidak pernah
   ditimpa — itu milik masing-masing aplikasi.

Seluruh penulisan penerima lewat query builder, bukan model. Itu yang memutus
lingkaran: perubahan dari seberang tidak memicu observer, jadi tidak dikirim
balik ke asalnya lalu dikirim balik lagi. Tes "perubahan dari seberang tidak
dikirim balik" menjaganya.

## Menjemput akun saat masuk

Pengiriman otomatis hanya membawa akun yang lahir atau berubah **setelah**
penautan menyala. Akun pesanvo yang sudah ada sebelumnya baru sampai ke WA
kalau `akun-tertaut:tautkan` dijalankan — dan sampai saat itu pemiliknya disuruh
mendaftar ulang dengan email dan kata sandi yang sebenarnya benar. Ini sudah
terjadi di produksi pertama.

Sekarang login WA yang gagal karena akunnya belum ada (atau kata sandinya
tertinggal) bertanya ke pesanvo lewat `POST /api/internal/akun-tertaut/cari`
(`{"email": …}`, tanda tangan sama). pesanvo menjawab 404 kalau tidak ada, 403
untuk akun admin, atau `{"data": <payload>}`. **Kata sandi yang diketik tidak
pernah dikirim**: yang diminta hanya hash, pencocokannya di WA. Cocok → akunnya
dibuat (atau diperbarui, dengan aturan verifikasi yang sama seperti di atas) dan
langsung masuk. Login Google melakukan hal yang sama tanpa kata sandi — Google
sudah membuktikan emailnya. Email yang tidak ditemukan tidak ditanyakan lagi
selama semenit, supaya formulir masuk tidak jadi banjir permintaan.

Arah sebaliknya (pesanvo menjemput dari WA) sengaja tidak dibuat: akun WA
dimulai dari database kosong dan setiap akun barunya langsung terkirim.

## Aturan pengirim

- Pemicunya observer model, bukan controller: akun lahir dan kata sandi berganti
  lewat banyak jalan (daftar, Google, checkout, order Shopee, pembayaran Lynk,
  lupa kata sandi, admin, rehash otomatis saat masuk). Yang dipasang per
  controller suatu saat terlewat di satu jalan.
- Yang dikirim: akun baru, kata sandi berganti, email berganti, email
  terverifikasi. Perubahan nama atau profil **tidak** dikirim.
- Setiap perubahan menandai `users.linked_sync_pending_at` lebih dulu — juga
  saat penautan mati, supaya perubahan selama masa mati ikut terkirim begitu
  env-nya diisi. Email lama disimpan di `linked_sync_previous_email` sampai
  terkirim; tanpa itu percobaan ulang membuat akun kedua di seberang.
- Job hanya membawa id pengguna, bukan hash-nya: hash tidak pernah mengendap di
  tabel `jobs`, dan yang terkirim selalu keadaan terbaru.
- **Penghapusan akun tidak diteruskan.** Menghapus akun gateway tidak boleh ikut
  menghapus akun VPS orang itu, dan sebaliknya.

## Menyalakan pertama kali

1. Isi kedua env di **kedua** aplikasi untuk tahap itu, deploy keduanya.
2. Jalankan sekali di **kedua** aplikasi: `php artisan akun-tertaut:tautkan`
   (`--dry-run` untuk menghitung dulu). Mode `link`: akun yang belum ada di
   seberang dibuatkan; akun yang sudah ada dengan email sama hanya ditautkan —
   kata sandinya **tidak ditimpa**, karena tidak ada cara mengetahui mana dari
   dua kata sandi berbeda yang masih diingat pemiliknya. Sejak penggantian
   kata sandi berikutnya, keduanya sama.
3. Pastikan penjadwal berjalan di kedua aplikasi — `akun-tertaut:kirim` yang
   mengulang kiriman yang gagal saat aplikasi seberang sedang deploy.

Cara memastikan jalurnya hidup: daftar akun uji di salah satu aplikasi, lalu
masuk di aplikasi lain dengan kata sandi yang sama. Kalau gagal, cek
`linked_sync_pending_at` akun itu (masih terisi = belum sampai) dan log
`Akun tertaut:` di kedua sisi.

## Keterbatasan yang disadari

- Dua penggantian kata sandi dalam detik yang sama saat pengiriman sedang
  berjalan bisa melepas penanda yang kedua; kata sandinya menyusul pada
  penggantian berikutnya. Kolom waktunya berpresisi detik.
- 2FA tidak ikut: aplikasi yang satu tidak bisa memverifikasi kode authenticator
  yang dipasang di aplikasi lain, dan menyalin rahasia TOTP antar aplikasi
  menggandakan tempat ia bisa bocor.
