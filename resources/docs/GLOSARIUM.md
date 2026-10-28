# Glosarium

Istilah yang dipakai di dashboard dan dokumentasi ini.

---

### API

Cara aplikasi Anda berbicara dengan gateway lewat permintaan HTTP. Lihat [Referensi API](REFERENSI_API.md).

### API key

Kunci yang dipakai aplikasi Anda untuk membuktikan diri saat memanggil gateway. Berbentuk `vwa_xxxxxxxx.xxxxxxxx`. Tersimpan terenkripsi dan bisa dibuka lagi kapan saja dari halaman API Keys oleh owner atau admin workspace.

### Antrean

Daftar tunggu pesan yang akan dikirim. Pesan tidak dikirim seketika — ia masuk antrean, menunggu jeda anti-blokir, baru dikirim. Karena itulah pengiriman massal memakan waktu.

### Batch ID

Penanda yang mengelompokkan semua pesan dari satu pengiriman massal, agar bisa dipantau sebagai satu kesatuan.

### Bulk

Pengiriman satu pesan ke banyak nomor sekaligus lewat satu permintaan API.

### Chat ID

Alamat percakapan di WhatsApp. Untuk perorangan berakhiran `@c.us`, untuk grup `@g.us`.

### Kuota

Batas jumlah pesan keluar per bulan sesuai paket Anda. Dihitung saat pesan diantrekan, bukan saat terkirim.

### Perangkat tertaut

Cara WhatsApp menghubungkan akun Anda ke perangkat lain — laptop, tablet, atau dalam hal ini gateway kami. Diatur lewat **Setelan → Perangkat Tertaut** di HP.

### Placeholder

Bagian template yang nilainya diisi belakangan, ditulis `{{ nama }}`. Lihat [Template Pesan](TEMPLATE_PESAN.md).

### QR code

Gambar yang di-scan dari HP untuk menautkan nomor WhatsApp Anda. Berganti sendiri setiap beberapa puluh detik demi keamanan.

### Rate limit

Batas jumlah permintaan API per menit untuk satu API key. Melewatinya menghasilkan balasan `429`.

### Retensi

Berapa lama riwayat pesan disimpan sebelum dihapus otomatis.

### Sesi

Satu nomor WhatsApp yang tertaut ke workspace Anda. Punya beberapa nomor berarti punya beberapa sesi.

### Signing secret

Kunci rahasia untuk memastikan kiriman webhook benar-benar berasal dari kami dan tidak diubah di jalan.

### Status pesan

Tahapan perjalanan sebuah pesan: *Antre* → *Mengirim* → *Terkirim* → *Sampai* → *Dibaca*, atau *Gagal*.

### Template

Susunan pesan yang disimpan dan dipakai berulang, dengan bagian yang berubah diisi lewat placeholder.

### ULID

Bentuk penanda unik yang dipakai untuk ID pesan dan ID sesi, misalnya `01K2CDEFGH7JKMNPQRSTVWXYZ`.

### Webhook

Kiriman otomatis dari gateway ke aplikasi Anda saat ada kejadian — pesan masuk, atau status pengiriman berubah. Kebalikan arah dari API. Lihat [Webhook](WEBHOOK.md).

### Workspace

Wadah yang menampung nomor, API key, template, dan riwayat pesan Anda. Satu akun bisa punya beberapa workspace, dan rekan tim bisa diundang ke dalamnya.
