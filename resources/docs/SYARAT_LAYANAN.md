# Syarat Layanan

**Berlaku sejak {{legal.effective_date}} · Versi {{legal.version}}**

Dokumen ini adalah perjanjian antara Anda dan {{legal.entity.name}} ("**{{legal.brand}}**", "kami"), berkedudukan di {{legal.entity.address}}, NIB {{legal.entity.nib}}, selaku penyelenggara layanan **{{legal.product}}** di {{legal.domain}} ("**Layanan**").

Dengan mendaftar, menautkan nomor WhatsApp, atau memakai API kami, Anda menyatakan telah membaca dan terikat pada syarat ini. Jika Anda mendaftar mewakili sebuah badan usaha, Anda menyatakan berwenang mengikat badan usaha tersebut.

Bacalah **Bagian 4** dengan saksama. Bagian itu memuat hal yang paling sering tidak disadari pelanggan baru dan paling berpotensi merugikan Anda jika dilewati.

---

## 1. Apa yang kami sediakan

{{legal.brand}} menyediakan gateway yang menghubungkan aplikasi Anda dengan WhatsApp: satu REST API untuk mengirim pesan, menerima pesan masuk, dan meneruskannya ke aplikasi Anda lewat webhook.

Layanan disediakan **sebagaimana adanya** dan **sebagaimana tersedia**, dengan janji ketersediaan yang dinyatakan terpisah di [SLA](sla).

## 2. Akun dan workspace

Anda wajib memberikan data yang benar saat mendaftar, menjaga kerahasiaan kata sandi dan API key, serta segera memberi tahu kami jika ada indikasi akses tanpa izin.

Seluruh aktivitas yang terjadi melalui akun dan API key Anda dianggap dilakukan oleh Anda. API key yang bocor dan dipakai orang lain untuk mengirim pesan tetap menjadi tanggung jawab Anda, termasuk kuota yang terpakai dan akibat dari isi pesannya. Kami menyediakan pencabutan kunci seketika di dashboard; gunakan itu begitu ada kecurigaan.

Satu akun boleh memiliki lebih dari satu workspace. Setiap workspace ditagih terpisah.

## 3. Paket, pembayaran, dan perpanjangan

Harga, kuota, dan batas setiap paket tercantum di halaman harga dan berlaku sebagaimana tertera saat tagihan Anda terbit.

Tagihan terbit sebelum periode berjalan berakhir. Pembayaran dilakukan lewat QRIS atau transfer bank, lalu **dicocokkan secara manual oleh tim kami** — belum ada gerbang pembayaran otomatis. Karena itu Anda wajib mengunggah bukti transfer, dan aktivasi dapat memakan waktu hingga satu hari kerja. Ketentuan lengkap, termasuk apa yang terjadi jika uang sudah masuk tetapi layanan belum aktif, ada di [Kebijakan Refund](kebijakan-refund).

Nominal tagihan mengandung **kode unik tiga digit** yang membedakannya dari tagihan lain. Membayar dengan nominal yang dibulatkan membuat pembayaran Anda tidak dapat dicocokkan.

Pengembalian dana diatur terpisah di [Kebijakan Refund](kebijakan-refund).

## 4. Hal yang wajib Anda pahami sebelum membeli

Empat hal berikut adalah sifat mendasar layanan ini. Semuanya kami nyatakan di muka karena tidak satu pun dapat kami hilangkan.

### 4.1 Ini bukan WhatsApp Business API resmi

Layanan ini bekerja dengan menautkan nomor Anda sebagai **perangkat tertaut** (linked device), cara yang sama seperti WhatsApp Web. Kami **bukan** Business Solution Provider resmi Meta dan tidak memakai WhatsApp Business Platform (Cloud API).

Konsekuensinya, dan ini nyata:

- **Nomor Anda dapat dibatasi atau diblokir oleh WhatsApp**, terutama bila dipakai mengirim pesan massal, pesan yang tidak diminta penerima, atau pesan yang banyak dilaporkan. Pemblokiran dilakukan Meta, bukan kami, dan **tidak dapat kami cegah, batalkan, atau ajukan bandingnya.**
- Perubahan sepihak pada WhatsApp dapat mengganggu atau menghentikan Layanan tanpa pemberitahuan sebelumnya.
- Kami tidak berafiliasi dengan, tidak didukung oleh, dan tidak disponsori oleh WhatsApp LLC atau Meta Platforms, Inc.

**Kami tidak bertanggung jawab atas pemblokiran nomor Anda oleh WhatsApp**, termasuk kehilangan pendapatan, pelanggan, atau riwayat percakapan yang mengikutinya. Cara memperkecil risikonya ada di [Praktik Baik](praktik-baik) dan wajib Anda ikuti.

Jika kebutuhan Anda menuntut jalur resmi Meta beserta jaminan yang menyertainya, Layanan ini bukan pilihan yang tepat, dan kami akan mengatakannya jika Anda bertanya.

### 4.2 Nomor Anda menempati kapasitas bersama

Jumlah nomor WhatsApp yang dapat aktif bersamaan pada platform kami **terbatas dan dibagi seluruh pelanggan**. Batas yang berlaku pada workspace Anda tertera di dashboard. Kami dapat menolak penautan nomor baru saat kapasitas penuh, termasuk pada paket yang secara nominal mengizinkannya.

### 4.3 Pesan Anda kami simpan, dan retensinya terbatas

Isi pesan yang dikirim dan diterima melalui Layanan disimpan pada sistem kami agar dapat Anda lihat kembali, lacak statusnya, dan teruskan ke aplikasi Anda. **Riwayat dipangkas otomatis** sesuai masa retensi paket Anda — 7 hari pada paket coba, hingga 365 hari pada paket tertinggi.

Pesan yang telah melewati masa retensi terhapus permanen dan **tidak dapat dipulihkan**. Jika riwayat percakapan penting bagi Anda, simpan salinannya sendiri melalui webhook atau fitur Ekspor Data.

### 4.4 Kami tidak menyaring isi pesan Anda

Kami tidak membaca, menyunting, atau memoderasi isi pesan Anda dalam pemakaian normal. Seluruh tanggung jawab atas isi pesan, keabsahan persetujuan penerima, dan kepatuhan pada peraturan yang berlaku ada pada Anda. Batasannya diatur di [Kebijakan Penggunaan Wajar](penggunaan-wajar).

## 5. Kewajiban Anda

Anda wajib:

- memakai Layanan hanya untuk tujuan yang sah;
- memiliki **persetujuan penerima** sebelum mengirim pesan kepada mereka;
- mematuhi [Kebijakan Penggunaan Wajar](penggunaan-wajar), Ketentuan Layanan WhatsApp, dan peraturan perundang-undangan Republik Indonesia, termasuk Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi;
- tidak berupaya membobol, membebani secara tidak wajar, merekayasa balik, atau mengakses bagian Layanan yang bukan hak Anda;
- tidak menjual kembali Layanan tanpa perjanjian kemitraan tertulis dengan kami.

## 6. Penangguhan dan penghentian

### 6.1 Karena tagihan

Jika tagihan lewat jatuh tempo, layanan workspace Anda ditangguhkan bertahap:

| Kapan | Yang berhenti |
|---|---|
| Jatuh tempo lewat | Pengiriman pesan keluar, pencatatan pesan masuk, dan penerusan webhook |
| 3 hari sesudahnya | Nomor Anda dilepas dari gateway |

**Pesan yang dikirim orang kepada Anda tidak hilang.** Kami adalah perangkat tertaut, sehingga pesan tetap sampai ke WhatsApp di ponsel Anda; yang berhenti hanyalah pencatatan dan penerusannya ke aplikasi Anda.

Setelah tagihan lunas, nomor Anda tersambung kembali **secara otomatis** dalam hitungan menit. Anda tidak perlu memindai QR ulang, karena kredensial sesi tidak kami hapus.

### 6.2 Karena pelanggaran

Kami dapat menangguhkan atau menghentikan akses seketika, tanpa pengembalian dana, bila Anda melanggar Bagian 5 atau [Kebijakan Penggunaan Wajar](penggunaan-wajar) secara berat — terutama pengiriman pesan massal tanpa persetujuan, penipuan, atau tindakan yang membahayakan pelanggan lain.

Untuk pelanggaran yang tidak berat dan dapat diperbaiki, kami memberi tahu Anda lebih dulu dan memberi waktu wajar untuk memperbaikinya.

### 6.3 Oleh Anda

Anda dapat berhenti kapan saja dengan tidak memperpanjang, menghapus workspace, atau menghapus akun dari halaman Profil. Penghapusan akun bersifat permanen setelah masa tunggu {{legal.retention.account_grace_days}} hari; rinciannya di [Kebijakan Privasi](kebijakan-privasi).

Biaya yang sudah dibayar untuk periode berjalan tidak dikembalikan, kecuali diatur lain di [Kebijakan Refund](kebijakan-refund).

## 7. Kekayaan intelektual

Layanan, perangkat lunak, dan dokumentasinya adalah milik kami. Anda memperoleh hak pakai yang terbatas, tidak eksklusif, dan tidak dapat dialihkan selama berlangganan.

**Data Anda tetap milik Anda.** Isi pesan, kontak, dan konfigurasi yang Anda masukkan tidak kami klaim kepemilikannya, tidak kami jual, dan tidak kami pakai untuk melatih model kecerdasan buatan.

## 8. Batasan tanggung jawab

Sepanjang diizinkan hukum yang berlaku:

- Kami tidak bertanggung jawab atas kerugian tidak langsung, termasuk kehilangan keuntungan, pendapatan, data, peluang usaha, atau reputasi.
- Kami tidak bertanggung jawab atas hal-hal di luar kendali wajar kami, termasuk **tindakan WhatsApp/Meta terhadap nomor Anda**, gangguan penyedia server, gangguan jaringan, dan keadaan kahar.
- **Total tanggung jawab kami** atas seluruh klaim yang timbul dari perjanjian ini dibatasi setinggi-tingginya sebesar **biaya langganan yang benar-benar Anda bayarkan kepada kami dalam 3 (tiga) bulan terakhir sebelum peristiwa yang menimbulkan klaim.**

Pembatasan ini tidak berlaku untuk kesengajaan atau kelalaian berat kami, dan tidak mengurangi hak Anda yang tidak dapat dikesampingkan menurut hukum Indonesia.

Sebagai {{legal.entity.form}}, harta perseroan terpisah dari harta pribadi pendirinya; klaim terhadap kami terbatas pada harta perseroan.

## 9. Ganti rugi

Anda membebaskan kami dari tuntutan pihak ketiga yang timbul karena isi pesan yang Anda kirim, pelanggaran Anda atas syarat ini, atau pemakaian Layanan oleh Anda yang melanggar hukum — termasuk tuntutan dari penerima pesan Anda dan dari otoritas pelindungan data.

## 10. Perubahan syarat

Kami dapat mengubah syarat ini. Perubahan yang **merugikan Anda secara material** diberitahukan sekurang-kurangnya **30 hari sebelum berlaku**, lewat email ke alamat penagihan Anda dan pemberitahuan di dashboard.

Jika Anda menolak perubahan itu, Anda berhak berhenti sebelum tanggal berlakunya dan memperoleh pengembalian dana **proporsional** atas sisa periode yang sudah dibayar. Melanjutkan pemakaian setelah tanggal berlaku berarti menerima perubahan tersebut.

## 11. Penyelesaian sengketa

Perjanjian ini tunduk pada hukum Republik Indonesia.

Setiap perselisihan diselesaikan lebih dulu secara **musyawarah** dalam jangka waktu {{legal.dispute.negotiation_days}} hari sejak salah satu pihak menyampaikan pemberitahuan tertulis.

Bila musyawarah tidak mencapai kesepakatan, perselisihan diselesaikan melalui **{{legal.dispute.forum}}** menurut peraturan dan prosedur BANI yang berlaku, dengan tempat arbitrase di {{legal.dispute.seat}} dan bahasa Indonesia.

> **Yang perlu Anda sadari:** putusan arbitrase bersifat **final dan mengikat**. Dengan menyetujui syarat ini, kedua pihak melepaskan hak untuk mengajukan banding atau kasasi. Biaya arbitrase juga jauh lebih besar daripada biaya pengadilan negeri, dan menjadi beban pihak yang kalah kecuali majelis memutus lain.

## 12. Ketentuan penutup

Jika ada ketentuan dalam dokumen ini yang dinyatakan tidak sah, ketentuan lainnya tetap berlaku.

Kami dapat mengalihkan perjanjian ini dalam rangka penggabungan, akuisisi, atau penjualan aset, dengan pemberitahuan kepada Anda. Anda tidak dapat mengalihkan perjanjian ini tanpa persetujuan tertulis kami.

Dokumen yang menjadi satu kesatuan dengan syarat ini: [Kebijakan Privasi](kebijakan-privasi), [Perjanjian Pemrosesan Data](dpa), [Kebijakan Penggunaan Wajar](penggunaan-wajar), [SLA](sla), dan [Kebijakan Refund](kebijakan-refund).

## 13. Menghubungi kami

{{legal.entity.name}}
{{legal.entity.address}}
NIB {{legal.entity.nib}} · NPWP {{legal.entity.npwp}}

Email: {{legal.contact.email}}
Urusan data pribadi: {{legal.contact.privacy_email}}
