# Glosarium

Istilah yang dipakai di seluruh kode dan dokumentasi.

---

### ack

Tanda terima dari WhatsApp. Angkanya bertingkat: `1` sampai ke server, `2` sampai ke HP tujuan, `3`/`4` dibaca. Dipetakan menjadi status `sent`, `delivered`, `read`.

Ack bisa datang tidak berurutan, karena itu status pesan dijaga agar hanya boleh maju.

### antrean (queue)

Ada dua, dan keduanya berbeda tujuan.

**Antrean Laravel** (tabel `jobs`) memisahkan pengiriman dari siklus permintaan dan memberi percobaan ulang.

**Antrean engine** (dalam memori, per sesi) memberi jeda antar pesan supaya nomor tidak diblokir. Ada di engine, bukan Laravel, supaya isolasinya per sesi — broadcast satu workspace tidak menahan pesan workspace lain.

### API key

Kredensial untuk memanggil REST API, dikirim lewat header `X-Api-Key`. Format `vwa_<prefix>.<rahasia>`. Disimpan dua kali: hash untuk memverifikasi permintaan, dan salinan terenkripsi (`key_ciphertext`, cast `encrypted`) supaya nilainya bisa ditampilkan lagi di dashboard kepada owner dan admin. Salinan terenkripsi dibuang saat kunci dicabut.

### backoff

Jeda yang membesar antar percobaan ulang. `SendMessageJob` memakai 10 detik, 1 menit, 5 menit — sesi yang baru putus butuh waktu untuk pulih, jadi mengulang secepat mungkin hanya membakar jatah percobaan.

### batch_id

ULID yang menandai semua pesan dari satu broadcast, sehingga bisa ditelusuri sebagai satu kesatuan.

### bootstrap (engine)

Proses saat engine menyala: menanyakan daftar sesi ke Laravel, memulihkan kredensialnya, lalu menyambungkan kembali. Inilah yang membuat deploy ulang tidak memaksa scan QR.

### chat id

Alamat percakapan di WhatsApp. Perorangan: `6281234567890@c.us`. Grup: `<id>@g.us`.

### driver

Cara pesan dikirim. Hanya ada satu: `wwebjs`. Kolom `wa_sessions.driver` mencatatnya, tapi tidak pernah ditawarkan sebagai pilihan ke pelanggan maupun diterima lewat API.

### engine

Program Node.js terpisah yang benar-benar berbicara dengan WhatsApp. Menjalankan Chromium lewat Puppeteer. Tidak punya database — semua yang perlu diingat ada di Laravel.

### HMAC

Tanda tangan berbasis secret bersama. Dipakai dua tempat: callback engine → Laravel, dan kiriman webhook → workspace. Membuktikan pengirimnya benar dan isinya tidak diubah di jalan.

### idempoten

Operasi yang aman diulang tanpa mengubah hasil. Callback engine dibuat idempoten supaya percobaan ulang tidak menggandakan pesan.

### kind (sesi) — sudah dihapus

Kolom `kind` dulu membedakan sesi `platform` (nomor Pesanvo) dari `tenant` (nomor pelanggan). Dihapus 13 Agustus 2026: nilai `platform` hanya bisa lahir dari perintah CLI, tidak terlihat di antarmuka mana pun, dan membuat sesi yang tampak hijau di dashboard tidak pernah terpilih otomatis saat pemanggil API mengosongkan `session_id`.

Sekarang semua sesi setara. OTP pun dikirim dari sesi terhubung milik workspace pemanggil, sama seperti pesan biasa — tidak ada nomor pengirim khusus dan tidak ada konfigurasi yang menunjuknya.

### kuota

Batas pesan keluar per bulan per workspace. Dihitung **saat pesan diantre**, bukan saat terkirim — kalau dihitung belakangan, satu workspace bisa mengantrekan puluhan ribu pesan sebelum ketahuan melewati batas.

### LocalAuth / RemoteAuth

Dua cara whatsapp-web.js menyimpan kredensial sesi.

**LocalAuth** hanya ke filesystem. Ini yang dipakai versi lama di app-erp, dan penyebab sesi hilang setiap deploy.

**RemoteAuth** menyimpan ke filesystem **dan** memampatkannya berkala ke penyimpanan luar lewat store yang bisa ditulis sendiri. Gateway ini memakainya dengan store yang mengirim ke Laravel.

### multi-tenant

Satu sistem melayani banyak pelanggan dengan data yang terpisah. Semua kueri berangkat dari workspace yang sedang aktif, bukan dari model global.

### normalisasi nomor

Mengubah `0812…`, `+62 812-…`, `62812…` menjadi satu bentuk `62812…`. Aturannya sengaja sama persis dengan `WhatsAppLink` di app-web supaya nomor yang sudah tersimpan di aplikasi lain tidak berubah arti.

### OTP

Kode sekali pakai untuk membuktikan kepemilikan nomor. Gateway yang membuat dan mengirimnya; app-auth yang menyimpan hasil verifikasinya sebagai bagian dari identitas.

### persistent volume

Penyimpanan yang hidupnya terpisah dari container. Container boleh dihapus dan dibuat ulang; isinya tetap. Di sinilah kredensial sesi disimpan.

### provider

Lihat **driver**. `WhatsAppProvider` adalah interface-nya di kode.

### Puppeteer

Library Node untuk mengendalikan Chromium secara terprogram. Dipakai whatsapp-web.js untuk membuka dan mengoperasikan WhatsApp Web.

### rate limit

Batas jumlah permintaan API per menit. Dihitung **per API key**, bukan per IP — beberapa aplikasi Pesanvo berjalan di VPS yang sama.

### retensi

Berapa lama data disimpan sebelum dipangkas otomatis. Pesan 90 hari, log webhook 30 hari (produksi). Angka pemakaian tetap aman karena `usage_counters` menyimpan agregat terpisah.

### scope

Pembatas kemampuan API key. `*` untuk semuanya, `otp` untuk endpoint OTP. Scope `otp` hanya diberikan ke app-auth.

### sesi

Satu nomor WhatsApp yang tertaut, beserta kredensialnya. Satu sesi = satu Chromium di engine.

### soft delete

Menandai baris sebagai terhapus (`deleted_at`) tanpa benar-benar membuangnya. Dipakai `workspaces` dan `wa_sessions`, memberi jeda sebelum penghapusan permanen.

### workspace (workspace)

Wadah yang memisahkan satu pelanggan dari yang lain. Nomor, API key, pesan, template, dan webhook semuanya milik satu workspace.

### ULID

Pengenal unik yang bisa diurutkan waktu, mirip UUID tapi lebih ringkas dan aman dipakai sebagai nama file. Dipakai `wa_sessions.id` dan `messages.id`.

### webhook

Kiriman HTTP dari gateway ke aplikasi workspace saat ada kejadian — pesan masuk, status berubah. Setiap kiriman ditandatangani HMAC.

### whatsapp-web.js

Library Node yang mengotomasi WhatsApp Web. Bukan API resmi: ia menjalankan browser sungguhan dan menekan tombolnya secara terprogram.
