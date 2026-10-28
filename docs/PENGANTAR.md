# Pengantar: Apa Itu Pesanvo Gateway

Dokumen ini untuk siapa pun yang baru menyentuh project ini — developer baru, calon rekan kerja, atau Anda sendiri enam bulan dari sekarang. Tidak ada asumsi pengetahuan sebelumnya.

---

## 1. Masalah yang diselesaikan

Aplikasi bisnis selalu perlu mengabari orang: invoice terbit, tiket dibalas, gaji sudah masuk, rapat sebentar lagi. Email bisa, tapi di Indonesia yang benar-benar dibaca adalah WhatsApp.

Masalahnya, mengirim WhatsApp dari sebuah aplikasi tidak sesederhana mengirim email. Tidak ada "SMTP untuk WhatsApp". Yang tersedia hanya dua jalan, dan keduanya punya konsekuensi (dibahas tuntas di [PERBANDINGAN_PROVIDER.md](PERBANDINGAN_PROVIDER.md)):

1. **Cara resmi** — WhatsApp Business API dari Meta, langsung atau lewat perantara seperti Twilio. Stabil dan resmi, tapi butuh verifikasi bisnis, nomor khusus, template yang harus disetujui, dan bayar per pesan.
2. **Cara tidak resmi** — mengendalikan WhatsApp Web lewat browser yang diotomasi. Gratis, nomor apa pun bisa, langsung jalan. Tapi tidak resmi, dan nomor berisiko diblokir.

Pesanvo memilih jalan kedua untuk sekarang, dengan pintu terbuka ke jalan pertama nanti.

### Kenapa tidak cukup menempelkannya di aplikasi yang sudah ada?

Sebelum gateway ini ada, WhatsApp memang sudah dipakai — ditempelkan di dalam `app-erp` sebagai proses Node kecil. Cara itu bekerja, sampai tidak.

Empat masalah yang muncul:

**Sesi hilang setiap deploy.** Kredensial nomor disimpan di dalam container aplikasi. Setiap kali kode di-deploy ulang, container dibuat ulang dari nol dan kredensialnya ikut terhapus. Akibatnya nomor harus di-scan ulang setiap deploy — dan di antara deploy dan scan berikutnya, semua notifikasi diam-diam gagal.

**Satu proses hanya bisa satu nomor.** Tidak ada cara memakai dua nomor, apalagi memberi pelanggan nomor mereka sendiri.

**Tidak ada isolasi.** Chromium memakan 300–500 MB RAM dan sesekali crash. Karena ia berjalan di container yang sama dengan aplikasi ERP, masalah WhatsApp ikut menjatuhkan ERP.

**Tidak ada pengamanan.** Endpoint pengirimnya terbuka tanpa autentikasi. Siapa pun yang bisa menjangkau port itu bisa mengirim WhatsApp atas nama perusahaan.

Pesanvo Gateway lahir untuk menyelesaikan keempatnya sekaligus.

---

## 2. Apa yang dilakukannya

Gateway ini duduk di tengah, antara aplikasi Anda dan WhatsApp:

```
Aplikasi Anda  ──HTTP──▶  Pesanvo Gateway  ──▶  WhatsApp
                                  │
                                  └──webhook──▶  Aplikasi Anda (pesan masuk)
```

Yang ia tangani sehingga aplikasi Anda tidak perlu:

- Menautkan dan menjaga koneksi nomor WhatsApp
- Menjalankan dan mengawasi browser Chromium
- Mengantre pesan dan memberi jeda supaya nomor tidak diblokir
- Mencoba ulang saat pengiriman gagal
- Mencatat riwayat dan status setiap pesan
- Meneruskan pesan masuk ke aplikasi Anda
- Memisahkan data antar pelanggan

Aplikasi Anda cukup tahu satu hal: **kirim POST ke satu URL dengan API key.**

---

## 3. Untuk siapa

Ada dua jenis pemakai, dan keduanya memakai sistem yang sama:

**Internal Pesanvo.** `app-erp` mengirim pengingat invoice dan tugas. `app-web` mengirim undangan anggota tim. `app-pricing` mengirim notifikasi langganan. `app-helpdesk` mengirim kabar tiket. `app-auth` mengirim kode OTP.

**Pelanggan luar (rencana).** Perusahaan yang butuh mengirim notifikasi WhatsApp dari sistem mereka sendiri, berlangganan lewat dashboard, menautkan nomor mereka sendiri, dan memakai REST API yang sama persis.

Karena itu sistem ini dibangun multi-tenant sejak baris pertama, bukan ditambahkan belakangan — memisahkan data pelanggan setelah sistem berjalan jauh lebih sulit daripada memisahkannya sejak awal.

---

## 4. Istilah

Enam kata yang muncul di mana-mana. Memahami ini cukup untuk membaca sisa dokumentasi.

### Workspace (workspace)

Wadah yang memisahkan satu pelanggan dari pelanggan lain. Nomor, API key, pesan, template, webhook — semuanya milik satu workspace, dan workspace lain tidak bisa melihatnya.

Anggota tim bisa diundang ke satu workspace dengan peran `owner`, `admin`, atau `member`.

### Sesi

Satu nomor WhatsApp yang tertaut. Isinya kredensial hasil scan QR — sama seperti WhatsApp Web mengingat browser Anda.

Ada dua jenis, dan pembedaannya penting:

| Jenis | Nomor | Dipakai untuk |
|---|---|---|
| `platform` | Nomor resmi Pesanvo | OTP, undangan anggota, notifikasi billing — pesan **atas nama Pesanvo** |
| `workspace` | Nomor pelanggan sendiri | Invoice ke customer, PO ke vendor — pesan **atas nama pelanggan** |

Kenapa dipisah? Customer sebuah perusahaan tidak mengenal Pesanvo. Invoice yang datang dari nomor asing terlihat seperti penipuan. Dan mengirim ratusan invoice per hari dari satu nomor platform adalah cara tercepat membuat nomor itu diblokir — kalau itu terjadi, OTP dan seluruh notifikasi Pesanvo ikut mati.

### Engine

Program Node.js terpisah yang benar-benar berbicara dengan WhatsApp. Ia menjalankan Chromium, membuka WhatsApp Web, dan menekan tombol-tombolnya secara otomatis. Satu sesi = satu Chromium.

Engine tidak punya database dan tidak menyimpan daftar sesi. Semuanya ia tanyakan ke Laravel saat menyala. Ini disengaja: container engine harus bisa dibuang dan dibangun ulang kapan saja tanpa kehilangan apa pun.

### API key

Kunci yang dipakai aplikasi untuk memanggil gateway, dikirim lewat header `X-Api-Key`. Formatnya `vwa_<prefix>.<rahasia>`.

Yang disimpan di server hanya hash-nya, jadi kunci penuh **hanya bisa dilihat sekali** saat dibuat. Satu aplikasi satu kunci, supaya kunci yang bocor bisa dicabut tanpa mengganggu yang lain.

### Webhook

Kebalikan arah dari API. Kalau ada yang membalas pesan Anda, gateway mengirim POST ke URL yang Anda daftarkan. Setiap kiriman ditandatangani supaya Anda bisa memastikan itu benar-benar dari Pesanvo.

### Driver

Cara pesan dikirim. Hanya ada satu: `wwebjs` (WhatsApp Web). Pelanggan tidak pernah diminta memilihnya — kolom `wa_sessions.driver` sekadar mencatat. Kenapa cuma satu, dan kapan sebuah kebutuhan sebaiknya tidak dilayani produk ini, ada di [PERBANDINGAN_PROVIDER.md](PERBANDINGAN_PROVIDER.md).

---

## 5. Bagaimana masalah "scan ulang tiap deploy" diselesaikan

Ini nilai jual utamanya, jadi layak dijelaskan pelan-pelan.

Kredensial sesi disimpan **dua kali**, di dua tempat dengan sifat berbeda:

**Pertama, di penyimpanan permanen (persistent volume).** Ini folder yang hidupnya terpisah dari container. Container boleh dihapus dan dibuat ulang; folder ini tetap ada. Ini menyelesaikan kasus sehari-hari: deploy ulang biasa.

**Kedua, dicadangkan ke database.** Setiap lima menit, engine memampatkan kredensialnya jadi satu file zip dan mengirimkannya ke Laravel untuk disimpan. Ini menyelesaikan kasus yang lebih jarang tapi lebih fatal: volume terhapus, server diganti, atau engine dipindah ke mesin lain.

Saat engine menyala, urutannya:

1. Tanya Laravel: sesi apa saja yang harus dijalankan?
2. Untuk tiap sesi, cek apakah kredensialnya masih ada di volume.
3. Kalau tidak ada, unduh cadangan terakhir dari Laravel.
4. Sambungkan kembali — tanpa QR.

Hasilnya: **nomor tidak terputus sendiri hanya karena server di-deploy ulang.**

### Yang bukan berarti nomor terkunci

Nomor tetap bisa diganti kapan saja. Di dashboard, klik **Putus tautan** pada sesi, lalu **Hubungkan** lagi dan scan QR dengan nomor mana pun — termasuk nomor yang berbeda.

Bedanya: itu terjadi karena **Anda** memutuskannya, bukan karena server kebetulan di-deploy.

---

## 6. Yang perlu diketahui sejak awal

Dua hal yang jujur harus disampaikan.

**Ini memakai WhatsApp Web, bukan API resmi.** Dokumentasi whatsapp-web.js sendiri menyatakan WhatsApp tidak mengizinkan klien tidak resmi. Nomor bisa diblokir, dan tidak ada jalur banding. Gateway menekan risikonya dengan jeda kirim otomatis, antrean terpisah per sesi, dan verifikasi nomor tujuan — tapi risikonya tidak nol. Kirimlah ke orang yang memang mengharapkan pesan Anda.

**Setiap sesi memakan RAM.** Satu Chromium ≈ 300–500 MB. Sepuluh nomor butuh server 4–5 GB. Ini yang membatasi berapa banyak pelanggan bisa ditampung satu server, dan harus masuk hitungan saat menetapkan harga.

---

## 7. Langkah berikutnya

- Ingin memahami cara kerja bagian dalamnya → [ARSITEKTUR.md](ARSITEKTUR.md)
- Ingin mulai menulis kode → [PANDUAN_DEVELOPER.md](PANDUAN_DEVELOPER.md)
- Ingin memanggil API-nya → [API.md](API.md)
- Ingin men-deploy → [DEPLOYMENT.md](DEPLOYMENT.md)
