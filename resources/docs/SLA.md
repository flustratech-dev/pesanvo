# Perjanjian Tingkat Layanan (SLA)

**Berlaku sejak {{legal.effective_date}} · Versi {{legal.version}}**

Dokumen ini menyatakan tingkat layanan yang kami janjikan, dan apa yang Anda peroleh bila kami tidak memenuhinya.

Angkanya sengaja kami tulis sebagai angka yang **benar-benar sanggup kami penuhi hari ini**, bukan angka yang enak dibaca. Menjanjikan 99,9% berarti menjanjikan waktu mati tidak lebih dari 43 menit sebulan — lebih pendek daripada satu deploy yang bermasalah. Janji yang dilanggar tiap bulan mengubah gangguan biasa menjadi wanprestasi, dan itu merugikan kedua pihak.

---

## 1. Untuk siapa SLA ini berlaku

| Paket | Berlaku? |
|---|---|
| Coba Gratis | Tidak |
| Essentials, Prime, Pay as you go | Ya, tanpa kompensasi kredit |
| Elite | Ya, dengan kompensasi kredit |
| Enterprise | Ya, dengan kompensasi kredit dan tanggapan prioritas |

Pelanggan Enterprise dapat menyepakati tingkat yang lebih tinggi secara tertulis; kesepakatan itu berlaku di atas dokumen ini.

## 2. Janji ketersediaan

Kami menargetkan ketersediaan bulanan **{{legal.sla.uptime_percent}}%** untuk komponen berikut:

| Komponen | Yang diukur |
|---|---|
| **API** | Endpoint `/api/v1/*` menjawab tanpa galat sisi server |
| **Dashboard** | Halaman dashboard dapat dibuka dan dipakai |
| **Antrean pengiriman** | Pesan yang diterima benar-benar diproses, bukan tertahan |
| **Webhook** | Pesan masuk diteruskan ke alamat Anda |

Ketersediaan dihitung per bulan kalender:

```
Ketersediaan = (Menit dalam bulan − Menit gangguan) ÷ Menit dalam bulan × 100
```

Keadaan sistem terkini dan riwayatnya dapat Anda lihat kapan saja di **[status.{{legal.domain}}]({{legal.domain}}/status)**.

## 3. Yang TIDAK dihitung sebagai gangguan

Bagian ini menentukan nilai sebenarnya dari janji di atas, dan karena itu kami tulis lengkap, bukan sebagai catatan kaki.

### 3.1 Tindakan WhatsApp terhadap nomor Anda

**Pemblokiran, pembatasan, atau pemutusan nomor Anda oleh WhatsApp/Meta bukan gangguan Layanan.** Kami bukan Business Solution Provider resmi dan tidak dapat mencegah, membatalkan, atau mengajukan bandingnya. Ini risiko yang melekat pada cara Layanan bekerja dan dinyatakan di [Syarat Layanan Bagian 4.1](syarat-layanan).

### 3.2 Perubahan pada WhatsApp

Perubahan sepihak pada protokol atau aplikasi WhatsApp yang mengganggu Layanan. Kami akan memperbaikinya secepat mungkin, tetapi waktu perbaikannya tidak dapat kami janjikan.

### 3.3 Pemeliharaan terjadwal

Diberitahukan **sekurang-kurangnya 48 jam sebelumnya** lewat email dan halaman status, dan sedapat mungkin dilakukan di luar jam kerja. Maksimal **4 jam per bulan**.

### 3.4 Pemeliharaan darurat

Untuk menutup celah keamanan atau menghentikan kerusakan yang sedang berjalan. Diberitahukan secepat mungkin, termasuk sesudahnya bila keadaan tidak memungkinkan.

### 3.5 Sebab dari sisi Anda

Kapasitas nomor penuh karena Anda melampaui batas paket; langganan tertangguh karena tagihan; pemakaian yang melanggar [Kebijakan Penggunaan Wajar](penggunaan-wajar); kesalahan konfigurasi pada aplikasi Anda; alamat webhook Anda yang tidak menjawab; ponsel tempat nomor Anda tertaut mati atau kehilangan jaringan.

### 3.6 Keadaan kahar

Bencana alam, perang, kerusuhan, pemadaman listrik atau jaringan berskala luas, tindakan pemerintah, dan pemutusan oleh penyedia server di luar kendali wajar kami.

## 4. Tanggapan dukungan

Jam layanan: **{{legal.sla.support_hours}}**.

Kami tidak menjanjikan dukungan 24/7. Menjanjikannya dengan tim sebesar tim kami berarti menjanjikan sesuatu yang tidak ada saat tim itu tidur.

| Tingkat | Artinya | Tanggapan pertama |
|---|---|---|
| **Kritis** | Layanan mati total, tidak ada pesan yang dapat dikirim siapa pun | {{legal.sla.response_hours.kritis}} jam kerja |
| **Tinggi** | Fungsi utama terganggu, ada jalan memutar | {{legal.sla.response_hours.tinggi}} jam kerja |
| **Normal** | Pertanyaan, permintaan fitur, gangguan kecil | {{legal.sla.response_hours.normal}} jam kerja |

Yang dijanjikan adalah **tanggapan pertama dari manusia**, bukan waktu selesainya perbaikan. Waktu perbaikan bergantung pada penyebabnya, dan sebagian penyebab — Bagian 3 — berada di luar kendali kami.

Tiket dibuat lewat menu **Bantuan** di dashboard. Tiket yang masuk di luar jam layanan dihitung mulai jam layanan berikutnya.

## 5. Kompensasi

Bila ketersediaan bulanan berada di bawah target, Anda berhak atas **kredit berupa perpanjangan masa langganan**:

| Ketersediaan bulan itu | Kredit |
|---|---|
| Di bawah 99,0% | 10% dari biaya langganan bulan tersebut |
| Di bawah 95,0% | 25% |
| Di bawah 90,0% | 50% |

Kredit diberikan sebagai perpanjangan masa langganan, **bukan pengembalian uang tunai**. Alasannya kami nyatakan terbuka: pengembalian tunai atas gangguan membuka klaim yang nilainya dapat jauh melampaui langganan itu sendiri, dan biaya seperti itu akhirnya ditanggung seluruh pelanggan lewat harga.

**Kredit tidak diberikan otomatis.** Anda mengajukannya lewat tiket dalam **{{legal.sla.claim_days}} hari** sejak akhir bulan yang bersangkutan, disertai perkiraan waktu gangguan dan dampaknya. Kami memeriksanya terhadap catatan kami dan menjawab dalam 7 hari kerja.

Kompensasi dalam dokumen ini adalah **satu-satunya ganti rugi** atas kegagalan memenuhi tingkat layanan, dan tunduk pada pembatasan di [Syarat Layanan Bagian 8](syarat-layanan).

## 6. Cadangan data

Basis data dicadangkan berkala dan cadangannya disimpan terpisah dari server utama.

Yang perlu Anda ketahui apa adanya: **kami tidak menjanjikan pemulihan data pada titik waktu tertentu (*point-in-time recovery*).** Bila terjadi kerusakan besar, pemulihan dilakukan dari cadangan terakhir yang tersedia, dan data yang masuk sesudah cadangan itu dapat hilang.

Riwayat pesan Anda juga dipangkas otomatis sesuai masa retensi paket. **Jika riwayat percakapan penting bagi usaha Anda, simpan salinannya sendiri** melalui webhook atau **Pengaturan → Ekspor Data**.

## 7. Perubahan SLA

Perubahan yang menurunkan tingkat layanan diberitahukan **30 hari sebelum berlaku**, dan Anda berhak berhenti dengan pengembalian proporsional sesuai [Syarat Layanan Bagian 10](syarat-layanan).

Kami menaikkan angka-angka dalam dokumen ini setelah ada redundansi nyata pada infrastruktur, bukan sebelumnya.

---

Pertanyaan mengenai SLA: {{legal.contact.email}}
