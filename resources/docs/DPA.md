# Perjanjian Pemrosesan Data

**Berlaku sejak {{legal.effective_date}} · Versi {{legal.version}}**

Perjanjian Pemrosesan Data ini ("**DPA**") melekat pada [Syarat Layanan](syarat-layanan) dan **berlaku otomatis** bagi setiap pelanggan {{legal.product}} sejak Layanan mulai dipakai. Anda tidak perlu menandatanganinya terpisah.

Jika bagian pengadaan atau hukum di perusahaan Anda meminta DPA yang ditandatangani basah atau memakai formulir mereka sendiri, kirimkan permintaannya ke {{legal.contact.privacy_email}}.

---

## 1. Para pihak dan kedudukannya

| | Pihak | Kedudukan menurut UU PDP |
|---|---|---|
| **Pengendali Data** | Anda, selaku pelanggan | Menentukan tujuan dan cara pemrosesan |
| **Prosesor Data** | {{legal.entity.name}} | Memproses **hanya atas perintah** Pengendali |

Pembagian ini berlaku atas **Data Pesan**: isi pesan WhatsApp yang Anda kirim dan terima melalui Layanan, nomor telepon lawan bicara Anda, lampiran, dan metadata pengirimannya.

Atas data akun Anda sendiri, kami bertindak sebagai Pengendali; hal itu diatur di [Kebijakan Privasi](kebijakan-privasi), bukan di sini.

> **Yang paling sering terlewat.** Karena Anda adalah Pengendali atas Data Pesan, **kewajiban terhadap penerima pesan Anda melekat pada Anda** — memastikan ada dasar pemrosesan yang sah, memberi tahu mereka, dan menjawab bila mereka menuntut haknya. Kami tidak punya hubungan hukum dengan mereka dan tidak dapat menjawab atas nama Anda.

## 2. Ruang lingkup pemrosesan

**Objek:** penyediaan gateway pengiriman dan penerimaan pesan WhatsApp.

**Jangka waktu:** selama Anda berlangganan, ditambah masa retensi pada Bagian 8.

**Sifat dan tujuan:** menerima perintah kirim dari aplikasi Anda, meneruskannya ke jaringan WhatsApp, menerima pesan masuk, mencatat status, meneruskan ke webhook Anda, dan menyimpan riwayat agar dapat Anda lihat kembali.

**Jenis data pribadi:** nomor telepon, nama profil WhatsApp, isi pesan, dan lampiran — yang isinya sepenuhnya ditentukan oleh Anda.

**Kategori subjek data:** pihak-pihak yang Anda hubungi, misalnya pelanggan, calon pelanggan, karyawan, atau pemasok Anda.

> **Peringatan yang harus Anda perhitungkan.** Kami tidak dapat mencegah Anda mengirim data pribadi yang bersifat spesifik (data kesehatan, data keuangan pribadi, data biometrik, data anak) melalui Layanan. Pasal 4 ayat (2) UU PDP menuntut pengamanan yang lebih ketat untuk data seperti itu. Layanan ini **tidak dirancang** untuk kategori tersebut, dan mengirimkannya adalah keputusan serta tanggung jawab Anda sepenuhnya.

## 3. Kewajiban kami sebagai Prosesor

Kami:

1. memproses Data Pesan **hanya berdasarkan perintah Anda** — yaitu panggilan API, konfigurasi, dan tindakan Anda di dashboard — kecuali diwajibkan lain oleh hukum, dan dalam hal itu kami memberi tahu Anda lebih dulu bila diizinkan;
2. **tidak** membaca, menganalisis, memonetisasi, atau memakai Data Pesan untuk melatih model kecerdasan buatan;
3. menjaga kerahasiaan dan mewajibkan setiap orang yang berwenang mengaksesnya terikat kerahasiaan;
4. menerapkan langkah pengamanan pada Bagian 5;
5. membantu Anda menjawab permintaan subjek data (Bagian 6);
6. memberi tahu Anda bila terjadi kebocoran (Bagian 7);
7. menghapus atau mengembalikan Data Pesan saat perjanjian berakhir (Bagian 8);
8. menyediakan keterangan yang Anda perlukan untuk membuktikan kepatuhan ini (Bagian 9).

## 4. Sub-prosesor

Anda memberikan **persetujuan umum** atas penggunaan sub-prosesor berikut:

| Sub-prosesor | Perannya | Data yang tersentuh | Lokasi |
|---|---|---|---|
| {{legal.hosting}} | Server aplikasi, basis data, dan penyimpanan berkas | Seluruh Data Pesan | {{legal.hosting_region}} |
| Brevo (Sendinblue SAS) | Pengiriman email pemberitahuan | Nama dan email penerima | Uni Eropa |

Setiap sub-prosesor terikat kewajiban pelindungan data yang **tidak lebih longgar** daripada DPA ini.

Bila kami hendak menambah atau mengganti sub-prosesor, Anda diberi tahu **sekurang-kurangnya 30 hari sebelumnya**. Bila Anda berkeberatan dengan alasan pelindungan data yang wajar, Anda berhak menghentikan langganan tanpa denda dan memperoleh pengembalian **proporsional** atas sisa periode yang sudah dibayar.

> **WhatsApp bukan sub-prosesor kami.** Meta memproses pesan Anda sebagai bagian dari layanan WhatsApp itu sendiri, berdasarkan hubungan antara Anda dan WhatsApp — bukan berdasarkan penunjukan oleh kami. Kami tidak dapat mengikat Meta pada DPA ini dan tidak menjanjikan apa pun atas nama mereka.

## 5. Langkah pengamanan

Sesuai Pasal 39 UU PDP, kami menerapkan sekurang-kurangnya:

- **Enkripsi saat transit** — seluruh lalu lintas melalui HTTPS/TLS;
- **Enkripsi saat disimpan** untuk data paling sensitif — API key dan kredensial sesi WhatsApp;
- **Pemisahan antar-pelanggan** yang ditegakkan pada setiap kueri, sehingga data satu workspace tidak dapat terbaca dari workspace lain;
- **Kendali akses** — panel administrasi terbatas pada akun tertentu dan **wajib** memakai autentikasi dua faktor;
- **Pencatatan audit** atas tindakan yang menyangkut uang, akses, dan konfigurasi;
- **Pembatasan laju** pada API untuk menahan penyalahgunaan;
- **Pemangkasan otomatis** riwayat pesan sesuai masa retensi, sehingga data tidak menumpuk melampaui keperluannya.

Kami dapat memperbarui langkah-langkah ini sepanjang tingkat pengamanannya tidak berkurang.

## 6. Membantu permintaan subjek data

Bila penerima pesan Anda menuntut haknya, **Anda yang wajib menjawab**. Kami membantu dengan menyediakan:

- **Ekspor mandiri** seluruh data workspace lewat **Pengaturan → Ekspor Data**, tersedia kapan saja tanpa perlu meminta kepada kami;
- **penghapusan mandiri** riwayat pesan dan workspace lewat dashboard;
- bantuan tambahan untuk hal yang tidak dapat Anda selesaikan sendiri, atas permintaan ke {{legal.contact.privacy_email}}, dijawab dalam **3×24 jam**.

Bila permintaan subjek data sampai kepada kami secara langsung, kami **tidak menjawabnya sendiri** — kami meneruskannya kepada Anda, karena Anda yang mengetahui dasar pemrosesannya.

## 7. Pemberitahuan kebocoran

Bila terjadi kebocoran Data Pesan, kami memberi tahu Anda **tanpa penundaan yang tidak wajar dan selambat-lambatnya 24 jam** sejak kami mengetahuinya — lebih cepat daripada tenggat 3×24 jam kepada Lembaga, agar Anda selaku Pengendali masih punya waktu memenuhi kewajiban Anda sendiri.

Pemberitahuan memuat: apa yang terjadi, kategori dan perkiraan jumlah data yang terdampak, akibat yang mungkin timbul, langkah yang sudah kami ambil, dan narahubung kami.

## 8. Pengembalian dan penghapusan

Selama berlangganan, riwayat pesan dipangkas otomatis sesuai masa retensi paket Anda (7–365 hari).

Saat langganan berakhir, Anda punya **{{legal.retention.account_grace_days}} hari** untuk mengunduh data Anda melalui fitur Ekspor. Setelah itu Data Pesan dihapus permanen dari sistem aktif kami.

Pengecualian yang harus dinyatakan terbuka: data tagihan dan bukti pembayaran tetap kami simpan {{legal.retention.billing_years}} tahun karena kewajiban perpajakan. Data itu **tidak memuat isi pesan Anda**.

## 9. Audit

Atas permintaan tertulis, kami menyediakan keterangan yang wajar untuk membuktikan kepatuhan pada DPA ini.

Untuk pelanggan dengan komitmen tahunan pada paket Enterprise, kami bersedia menjawab kuesioner keamanan dan — bila benar-benar diperlukan — memfasilitasi audit **sekali dalam satu tahun**, dengan pemberitahuan 30 hari sebelumnya, pada jam kerja, dan tidak mengganggu pelanggan lain. Biaya audit ditanggung Anda kecuali audit itu menemukan ketidakpatuhan yang material.

## 10. Tanggung jawab

Tanggung jawab yang timbul dari DPA ini tunduk pada pembatasan di [Syarat Layanan Bagian 8](syarat-layanan).

## 11. Hubungan dengan dokumen lain

Bila terjadi pertentangan mengenai pemrosesan data pribadi, DPA ini yang berlaku di atas [Syarat Layanan](syarat-layanan).

---

**Prosesor Data**
{{legal.entity.name}}
{{legal.entity.address}}
NIB {{legal.entity.nib}}
Narahubung pelindungan data: {{legal.contact.privacy_email}}
