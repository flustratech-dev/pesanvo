# Tanya Jawab

---

## Umum

### Apa bedanya dengan tombol "Chat via WhatsApp" di website?

Tombol `wa.me` hanya **membuka** aplikasi WhatsApp dengan pesan yang sudah terisi — manusia tetap harus menekan kirim. Gateway ini benar-benar mengirimkannya, otomatis, tanpa ada orang yang menekan apa pun.

### Apakah ini resmi dari WhatsApp?

Bukan. Nomor Anda tertaut sebagai perangkat tertaut, seperti WhatsApp Web di laptop. WhatsApp tidak secara resmi mengizinkan pengiriman otomatis lewat jalur ini, jadi nomor berisiko diblokir bila dipakai sembarangan.

Cara menghindarinya ada di [Praktik Baik](PRAKTIK_BAIK.md). Ringkasnya: kirim hanya kepada orang yang mengharapkan pesan Anda.

### Kenapa tidak pakai WhatsApp Business API resmi saja?

Jalur resmi butuh verifikasi bisnis Meta, nomor khusus yang belum pernah dipakai WhatsApp biasa, template yang harus disetujui lebih dulu, dan biaya per pesan.

Untuk sebagian besar kebutuhan — notifikasi pesanan, pengingat invoice, balasan pelanggan — jalur ini jauh lebih cepat dipakai dan tanpa biaya per pesan.

Kalau volume Anda sudah sangat besar atau komunikasinya tidak boleh gagal sama sekali, hubungi kami untuk membahas jalur resmi.

### Apakah WhatsApp saya masih bisa dipakai normal?

Bisa. Gateway berjalan sebagai perangkat tertaut, sama seperti WhatsApp Web. Anda tetap bisa chat seperti biasa dari HP.

Pesan yang dikirim gateway akan muncul di riwayat chat HP Anda.

---

## Nomor & sesi

### Apakah nomor saya terkunci? Bisa diganti?

**Bisa, kapan saja.** Klik **Putus tautan / ganti nomor** pada sesi, lalu **Hubungkan** dan scan QR dengan nomor mana pun.

Riwayat pesan, API key, dan webhook tetap utuh. ID sesi tidak berubah, jadi aplikasi Anda tidak perlu diubah sama sekali.

Yang kami jamin justru sebaliknya: nomor Anda **tidak akan terputus sendiri** hanya karena sistem kami diperbarui.

### Apakah HP harus selalu online?

Tidak terus-menerus, tapi perlu online sesekali. WhatsApp memutus perangkat tertaut yang tidak pernah tersinkron dalam waktu lama — sekitar dua minggu.

### Bisakah satu nomor dipakai di dua sesi?

Tidak. Satu nomor hanya bisa tertaut ke satu sesi aktif. Men-scan nomor yang sama di sesi kedua akan memutus sesi pertama.

Kalau butuh beberapa jalur pengiriman, gunakan nomor berbeda.

### Kenapa sesi saya terputus sendiri?

Paling sering karena HP lama tidak online, atau ada yang mengeluarkan perangkat lewat menu Perangkat Tertaut di HP.

Sistem menyambungkan ulang otomatis setiap menit. Kalau tidak pulih juga, kredensialnya sudah dicabut dari sisi WhatsApp dan perlu scan ulang.

### Bisakah kirim ke grup?

Bisa, dengan mengirim ID grup (berakhiran `@g.us`) sebagai tujuan. Cara mendapatkannya: kirim satu pesan ke grup itu dari HP Anda, lalu lihat kolom Nomor pada pesan masuk di Riwayat Pesan.

---

## Mengirim pesan

### Kenapa pengiriman massal lambat sekali?

Setiap pesan diberi jeda beberapa detik. Broadcast 500 nomor wajar memakan 30–60 menit.

Ini disengaja dan tidak bisa dimatikan. Mengirim beruntun tanpa jeda adalah cara tercepat membuat nomor Anda diblokir — jeda inilah yang menjaganya tetap hidup.

### Apa arti status pesan?

| Status | Arti |
|---|---|
| Antre | Sudah kami terima, menunggu giliran |
| Mengirim | Sedang dikirim |
| Terkirim | Sampai ke server WhatsApp (satu centang) |
| Sampai | Sampai ke HP tujuan (dua centang) |
| Dibaca | Dibaca (dua centang biru) |
| Gagal | Alasannya ada di detail pesan |

*Sampai* dan *Dibaca* hanya muncul kalau penerima mengaktifkan laporan dibaca.

### Kenapa pesan saya gagal?

Tiga alasan tersering: nomor tujuan tidak terdaftar di WhatsApp, sesi terputus dan tidak pulih dalam batas percobaan ulang, atau format nomornya tidak valid.

Alasan persisnya ada di detail pesan.

### Apakah pesan hilang kalau sesi sedang putus?

Tidak. Pesan dikembalikan ke antrean dan dicoba lagi sampai empat kali dengan jeda menaik. Sesi yang putus biasanya pulih dalam hitungan detik.

### Nomor ditulis 0812 atau 62812?

Keduanya diterima. Kami menyeragamkannya otomatis, termasuk bentuk `+62 812-3456-7890`.

### Bisakah menjadwalkan pesan untuk dikirim nanti?

Belum ada di dashboard. Untuk sekarang, jadwalkan dari sisi aplikasi Anda — panggil API kami pada waktu yang diinginkan.

---

## API & integrasi

### API key saya hilang, bagaimana?

Tidak bisa ditampilkan lagi — kami hanya menyimpan sidik jarinya. Buat kunci baru, perbarui aplikasi Anda, lalu cabut yang lama.

### Kenapa satu kunci per aplikasi?

Supaya kunci yang bocor bisa dicabut tanpa mengganggu aplikasi lain. Kalau semua berbagi satu kunci, mencabutnya berarti mematikan semuanya sekaligus.

### Kenapa API membalas 202, bukan 200?

`202` berarti "diterima untuk diproses". Pesan masuk antrean dan dikirim di latar belakang — untuk pengiriman massal, itu bisa memakan waktu lama.

Status sebenarnya ditelusuri lewat ID pesan yang dikembalikan, atau lewat webhook.

### Bagaimana memastikan webhook benar-benar dari Pesanvo?

Verifikasi header `X-Pesanvo-Signature` dengan signing secret webhook Anda. Contoh kodenya ada di [Webhook](WEBHOOK.md).

Tanpa verifikasi ini, siapa pun yang tahu URL webhook Anda bisa mengirim data palsu.

### Endpoint webhook saya sempat mati, apakah datanya hilang?

Kiriman diulang 3 kali dengan jeda menaik. Setelah 50 kegagalan berturut-turut, webhook dinonaktifkan otomatis — nyalakan lagi dari dashboard setelah endpoint pulih.

Pesan masuknya sendiri tetap tersimpan di Riwayat Pesan.

### Apakah ada SDK resmi?

Belum. Tapi API-nya HTTP + JSON biasa, dan [Contoh Integrasi](CONTOH_INTEGRASI.md) menyediakan kode siap salin untuk PHP, Node.js, dan Python.

---

## Akun & tagihan

### Bisakah satu akun punya beberapa workspace?

Bisa. Setiap workspace punya nomor, API key, dan riwayat pesannya sendiri yang terpisah.

Berguna kalau Anda mengelola beberapa merek atau beberapa klien.

Cara membuatnya: klik **+ Workspace** di sebelah pemilih workspace pada bagian atas halaman. Anda tidak perlu mendaftarkan akun kedua — berpindah antar workspace cukup lewat pemilih yang sama.

### Bagaimana mengundang rekan tim?

Menu **Pengaturan** → bagian Anggota. Mereka harus sudah pernah membuat akun di sini lebih dulu.

Peran **Member** hanya bisa melihat dan mengirim pesan; **Admin** bisa mengelola sesi dan API key.

### Apakah kuota saya hangus di akhir bulan?

Ya, kuota disetel ulang setiap awal bulan kalender dan tidak diakumulasi.

### Bagaimana menaikkan batas?

Kirim tiket dari menu **Bantuan** di dashboard — lihat [Bantuan & Tiket](BANTUAN.md). Bantuan tetap terbuka walau langganan Anda sedang tidak aktif.

---

## Data & privasi

### Apakah Pesanvo membaca pesan saya?

Isi pesan tersimpan di riwayat agar Anda bisa menelusurinya dan agar status pengiriman bisa dilacak. Kami tidak membacanya untuk keperluan lain.

### Berapa lama pesan disimpan?

Sesuai masa retensi paket Anda, lalu dipangkas otomatis. Kalau butuh menyimpan lebih lama, salin ke sistem Anda sendiri lewat API atau webhook.

### Apakah nomor pelanggan saya aman?

Data workspace Anda terpisah sepenuhnya dari workspace lain. Nomor telepon disamarkan di catatan teknis kami.

Perlu diingat: siapa pun yang Anda undang ke workspace bisa melihat riwayat pesan. Pertimbangkan itu saat memberi akses.
