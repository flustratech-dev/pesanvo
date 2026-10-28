# Kebijakan Privasi

**Berlaku sejak {{legal.effective_date}} · Versi {{legal.version}}**

Kebijakan ini menjelaskan bagaimana {{legal.entity.name}} ("**{{legal.brand}}**", "kami") memperlakukan data pribadi dalam penyelenggaraan **{{legal.product}}** di {{legal.domain}}.

Kami tunduk pada **Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi** ("UU PDP").

---

## 1. Dua peran kami, dan mengapa perbedaannya penting

Layanan ini memproses dua jenis data yang kedudukan hukumnya berbeda. Menyamakannya adalah kekeliruan yang paling sering terjadi, dan akibatnya jatuh kepada Anda.

**Sebagai Pengendali Data**, atas data akun Anda sendiri — nama, email, nomor telepon, data penagihan. Kami yang menentukan tujuan dan cara pemrosesannya. Bagian 2–9 dokumen ini mengatur hal itu.

**Sebagai Prosesor Data**, atas **isi pesan WhatsApp** yang Anda kirim dan terima beserta nomor lawan bicara Anda. Di sini **Anda** yang menjadi Pengendali: Anda yang memutuskan siapa dihubungi, isi pesannya apa, dan atas dasar apa. Kami hanya memproses atas perintah Anda.

Konsekuensi yang perlu Anda sadari: **kewajiban terhadap penerima pesan Anda ada pada Anda**, bukan pada kami — termasuk kewajiban memperoleh persetujuan yang sah dan menjawab bila mereka meminta datanya dihapus. Ketentuan lengkapnya ada di [Perjanjian Pemrosesan Data (DPA)](dpa), yang berlaku otomatis bagi setiap pelanggan tanpa perlu ditandatangani terpisah.

## 2. Data yang kami kumpulkan

### 2.1 Data yang Anda berikan

| Data | Dari mana | Untuk apa |
|---|---|---|
| Nama, email, kata sandi | Formulir pendaftaran | Membuat dan mengamankan akun |
| Nomor telepon, perusahaan, kota, alamat | Profil dan data penagihan | Menerbitkan tagihan, mengabari Anda |
| Bukti transfer | Halaman pembayaran | Mencocokkan pembayaran secara manual |
| Isi tiket bantuan dan lampirannya | Helpdesk | Menjawab pertanyaan Anda |

### 2.2 Data dari Google

Jika Anda masuk memakai Google, kami menerima **nama, alamat email, dan foto profil** Anda. Kami **tidak** memperoleh akses ke email, kontak, atau berkas Google Anda.

### 2.3 Data yang timbul dari pemakaian

Waktu masuk terakhir, alamat IP dan jenis peramban saat mengakses dashboard, catatan audit tindakan penting (mengubah paket, mencabut API key, menandai tagihan lunas), serta catatan teknis pemakaian API.

### 2.4 Isi pesan WhatsApp Anda

Kami menyimpan isi pesan yang dikirim dan diterima melalui Layanan, nomor pengirim dan penerima, status pengiriman, serta lampiran yang menyertainya. Ini kami lakukan **atas perintah Anda** agar Anda dapat melihat riwayat, melacak status, dan menerima pesan masuk lewat webhook.

Isi pesan **tidak** kami baca, analisis, jual, atau pakai untuk melatih model kecerdasan buatan.

### 2.5 Kredensial sesi WhatsApp

Saat Anda menautkan nomor, WhatsApp menerbitkan kredensial perangkat tertaut yang kami simpan dalam bentuk terenkripsi agar nomor Anda tidak perlu dipindai ulang setiap kali sistem kami di-deploy.

**Kredensial ini setara dengan WhatsApp Web yang selalu terbuka.** Kami menjaganya dengan enkripsi dan akses terbatas, dan menghapusnya saat Anda menekan **Putus tautan** atau menghapus sesi.

## 3. Dasar hukum pemrosesan

Sesuai Pasal 20 UU PDP:

| Dasar | Untuk apa |
|---|---|
| Pelaksanaan perjanjian | Menyediakan Layanan, menerbitkan tagihan, memberi dukungan |
| Kewajiban hukum | Menyimpan dokumen pembukuan dan perpajakan |
| Kepentingan sah | Menjaga keamanan, mencegah penyalahgunaan, memperbaiki Layanan |
| Persetujuan | Pesan pemasaran, jika ada — dan dapat Anda tarik kapan saja |

## 4. Kepada siapa data dibagikan

Kami **tidak menjual data pribadi kepada siapa pun**.

Data dibagikan hanya kepada penyedia berikut, sebatas yang diperlukan agar Layanan berjalan:

| Penyedia | Perannya | Data yang tersentuh | Lokasi |
|---|---|---|---|
| {{legal.hosting}} | Server tempat aplikasi dan basis data berjalan | Seluruh data Layanan | {{legal.hosting_region}} |
| Brevo (Sendinblue SAS) | Pengiriman email | Nama dan email penerima, isi email | Uni Eropa |
| Google LLC | Masuk dengan Google | Nama, email, foto profil | Amerika Serikat |
| WhatsApp LLC / Meta | Jaringan WhatsApp itu sendiri | Isi pesan dan nomor, sebagaimana melekat pada layanan WhatsApp | Global |

Daftar ini kami perbarui bila berubah, dan perubahannya diberitahukan sesuai Bagian 10.

Kami juga dapat mengungkapkan data bila diwajibkan oleh hukum atau permintaan resmi aparat yang berwenang. Bila diizinkan, kami akan memberi tahu Anda lebih dulu.

## 5. Transfer ke luar wilayah Indonesia

Sebagian penyedia di atas berada di luar Indonesia. Sesuai Pasal 56 UU PDP, transfer dilakukan ke negara dengan tingkat pelindungan yang setara atau dengan pengamanan kontraktual yang memadai (*Standard Contractual Clauses* atau setara).

## 6. Berapa lama data disimpan

| Data | Lama | Alasan |
|---|---|---|
| Isi pesan WhatsApp | 7–365 hari, sesuai paket Anda | Dipangkas otomatis; **tidak dapat dipulihkan** setelah terhapus |
| Data akun dan workspace | Selama akun aktif | |
| Setelah permintaan hapus akun | {{legal.retention.account_grace_days}} hari lalu dihapus permanen | Masa tunggu agar penghapusan yang keliru masih dapat dibatalkan |
| Catatan audit | {{legal.retention.audit_log_days}} hari | Menelusuri tindakan yang menyangkut uang dan akses |
| Tagihan dan bukti pembayaran | {{legal.retention.billing_years}} tahun | **Kewajiban perpajakan.** Data ini tetap kami simpan meskipun Anda meminta penghapusan — kami tidak berwenang mengesampingkannya |
| Kredensial sesi WhatsApp | Sampai Anda memutus tautan | |

## 7. Keamanan

Yang kami terapkan: seluruh lalu lintas terenkripsi (HTTPS), kata sandi disimpan sebagai *hash* dan tidak dapat dibaca siapa pun termasuk kami, API key disimpan terenkripsi, kredensial WhatsApp terenkripsi, akses ke panel admin terbatas dan tercatat, autentikasi dua faktor (TOTP) tersedia untuk seluruh pengguna dan **wajib** bagi administrator kami, serta pemisahan data antar-workspace yang ditegakkan di setiap kueri.

Tidak ada sistem yang sepenuhnya aman. Jika terjadi kebocoran data pribadi, kami memberi tahu Anda dan Lembaga yang berwenang **dalam 3×24 jam** sejak diketahui, sebagaimana diwajibkan Pasal 46 UU PDP, disertai keterangan data apa yang terdampak dan langkah yang kami ambil.

## 8. Hak Anda

Sesuai Pasal 5–15 UU PDP, Anda berhak untuk:

- **mengetahui** data apa yang kami proses dan atas dasar apa;
- **mengakses dan memperoleh salinannya** — tersedia mandiri lewat **Profil → Ekspor Data**, tanpa perlu meminta kepada kami;
- **memperbaiki** data yang keliru — lewat Profil dan Pengaturan Workspace;
- **menghapus** data Anda — lewat **Profil → Hapus Akun**, dengan pengecualian pada Bagian 6;
- **menarik persetujuan** yang pernah diberikan;
- **menolak** pengambilan keputusan yang semata-mata otomatis. Kami tidak melakukannya;
- **mengajukan keberatan** kepada kami, dan bila tidak puas, kepada Lembaga Pelindungan Data Pribadi.

Permintaan yang tidak dapat Anda selesaikan sendiri dapat dikirim ke **{{legal.contact.privacy_email}}**. Kami menjawab paling lambat **3×24 jam** sejak permintaan diterima, sebagaimana diwajibkan UU PDP. Kami dapat meminta verifikasi identitas lebih dulu — tanpa itu, siapa pun yang mengetahui alamat email Anda dapat meminta salinan data Anda.

## 9. Anak

Layanan ini tidak ditujukan bagi anak di bawah 18 tahun dan kami tidak dengan sengaja mengumpulkan data mereka. Bila terlanjur terkumpul, beri tahu kami dan akan kami hapus.

## 10. Perubahan kebijakan

Perubahan yang material diberitahukan sekurang-kurangnya **30 hari sebelum berlaku** lewat email dan pemberitahuan di dashboard. Versi dan tanggal berlaku selalu tertera di bagian atas halaman ini.

## 11. Menghubungi kami

{{legal.entity.name}}
{{legal.entity.address}}

Urusan data pribadi: **{{legal.contact.privacy_email}}**
Pertanyaan umum: {{legal.contact.email}}
