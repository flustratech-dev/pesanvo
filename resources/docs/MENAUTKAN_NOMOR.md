# Menautkan Nomor WhatsApp

Cara menghubungkan nomor, memantau keadaannya, dan menggantinya.

---

## Apa itu sesi

Satu **sesi** mewakili satu nomor WhatsApp yang tertaut. Kalau Anda punya tiga nomor untuk keperluan berbeda — misalnya CS, penagihan, dan pengumuman — buat tiga sesi.

Setiap sesi berjalan sendiri-sendiri. Satu nomor bermasalah tidak menghentikan nomor lain.

Berapa banyak sesi yang bisa dibuat tergantung paket Anda. Lihat **Pengaturan** untuk batas workspace Anda saat ini.

## Menautkan

1. **Sesi WhatsApp** → isi nama → **Buat sesi**
2. **Hubungkan** — sistem menyiapkan koneksi, biasanya 5–15 detik
3. QR muncul di layar
4. Di HP: WhatsApp → **Setelan** → **Perangkat Tertaut** → **Tautkan Perangkat**
5. Scan

Cara kerjanya sama persis seperti membuka WhatsApp Web di laptop. Nomor Anda tetap bisa dipakai normal di HP.

> QR berganti sendiri setiap beberapa puluh detik. Kalau terlewat, tunggu sebentar — yang baru muncul otomatis, tidak perlu menutup jendelanya.

## Arti status sesi

| Status | Arti | Yang perlu dilakukan |
|---|---|---|
| **Belum dijalankan** | Sesi dibuat, belum pernah dihubungkan | Klik Hubungkan |
| **Menghubungkan** | Sedang menyiapkan koneksi | Tunggu |
| **Menunggu scan QR** | QR siap di-scan | Scan dengan HP |
| **Terhubung** | Siap mengirim | — |
| **Terputus** | Koneksi terputus | Biasanya pulih sendiri dalam semenit |
| **Gagal** | Ada masalah | Lihat pesan galat di kartu sesi |

## Kalau sesi terputus

Sistem memeriksa setiap menit dan menyambungkan kembali secara otomatis. Sebagian besar gangguan pulih sendiri tanpa Anda perlu melakukan apa pun.

Pesan yang sedang dikirim saat sesi terputus **tidak hilang** — ia dikembalikan ke antrean dan terkirim begitu sesi pulih.

Penyebab tersering sesi terputus:

- **HP lama tidak online.** WhatsApp memutus perangkat tertaut yang tidak pernah tersinkron dalam waktu lama (sekitar 14 hari). Cukup buka WhatsApp di HP sesekali.
- **Perangkat dikeluarkan dari HP.** Seseorang membuka Perangkat Tertaut dan menekan keluar. Perlu scan ulang.
- **Nomor yang sama di-scan di tempat lain.** Satu nomor hanya bisa tertaut ke satu sesi aktif.

## Mengganti nomor

Nomor Anda tidak terkunci di sistem kami.

1. Klik **Putus tautan / ganti nomor** pada sesi
2. Klik **Hubungkan**
3. Scan QR dengan nomor yang baru

Yang tetap utuh setelah ganti nomor:

- Seluruh riwayat pesan
- Semua API key
- Semua webhook
- ID sesi — **aplikasi yang memakainya tidak perlu diubah sama sekali**

Yang berubah hanya nomor pengirimnya.

## Menghentikan sementara

**Hentikan** mematikan sesi tanpa memutus tautan. Kredensialnya tetap tersimpan, jadi menghubungkan lagi **tidak perlu** scan QR.

Berguna saat Anda ingin menghentikan pengiriman sementara tanpa kehilangan tautan nomor.

## Satu nomor, satu sesi

WhatsApp hanya mengizinkan satu tautan aktif per nomor di sistem kami. Kalau Anda men-scan nomor yang sama di sesi kedua, sesi pertama akan terputus.

Kalau butuh beberapa jalur pengiriman, gunakan nomor yang berbeda.

## Menghapus sesi

**Hapus** memutus tautan lalu membuang sesinya. Riwayat pesan yang pernah dikirim lewat sesi itu tetap ada di **Riwayat Pesan**.

Tindakan ini tidak bisa dibatalkan.
