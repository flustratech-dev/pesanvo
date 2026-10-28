# Bantuan & Tiket

Cara menghubungi kami, dan apa yang terjadi setelah tiket Anda terkirim.

---

## Mengirim tiket

Menu **Bantuan** di dashboard. Isi judul, pilih kategori, tulis apa yang terjadi, lalu kirim.

**Bantuan tetap terbuka walau langganan Anda sedang tidak aktif.** Justru saat layanan berhenti Anda paling butuh menghubungi kami — jadi membuat dan membalas tiket tidak pernah dihalangi status tagihan.

### Menulis tiket yang cepat selesai

Yang paling menghemat waktu Anda sendiri adalah menyebutkan tiga hal:

1. **Apa yang Anda lakukan** — endpoint yang dipanggil, tombol yang ditekan, atau nomor yang dituju.
2. **Apa yang terjadi** — salin pesan galatnya apa adanya, jangan diringkas. Kode status HTTP dan `message` dari jawaban API kami adalah petunjuk paling langsung.
3. **Kapan** — perkiraan jam kejadiannya sudah cukup untuk menemukan jejaknya di catatan kami.

Kalau menyangkut satu pesan tertentu, sertakan `message_id`-nya dari menu **Riwayat Pesan**.

### Prioritas

| Pilihan | Untuk keadaan |
|---|---|
| Rendah | Pertanyaan yang tidak menghambat apa pun |
| Normal | Ada yang tidak berjalan, tapi ada jalan lain |
| Tinggi | Layanan berhenti — pesan tidak terkirim sama sekali |

Pilih **Tinggi** hanya untuk yang memang menghentikan pekerjaan Anda. Kalau semua tiket bertanda tinggi, tandanya berhenti berarti apa-apa dan yang benar-benar mendesak ikut tenggelam.

---

## Lampiran

Tangkapan layar sering menjelaskan lebih cepat daripada paragraf. Yang diterima: **JPG, PNG, WEBP, PDF, TXT, dan LOG**, maksimal **5 MB** per berkas.

Lampiran disimpan di penyimpanan tertutup dan hanya bisa dibuka oleh anggota workspace Anda dan tim kami. Alamatnya tidak bisa ditebak dan tidak pernah terbuka untuk umum.

> **Periksa dulu sebelum melampirkan.** Tangkapan layar dashboard sering ikut memuat API key Anda. Kalau terlanjur ikut terkirim, cabut kuncinya dari menu **API Keys** dan buat yang baru — mencabut kunci tidak menghapus riwayat pesan Anda.

---

## Setelah tiket terkirim

Kami mengabari Anda lewat **WhatsApp dan email** begitu ada jawaban, ke nomor dan alamat yang Anda isi di **Pengaturan**. Isi jawabannya sendiri tidak ikut dikirim — hanya kabar bahwa ada balasan — karena tiket sering memuat hal yang tidak seharusnya diteruskan ke mana-mana.

Keadaan tiket:

| Keadaan | Artinya |
|---|---|
| Menunggu jawaban | Bola ada di kami |
| Sudah dijawab | Bola ada di Anda |
| Selesai | Ditutup, tapi masih bisa dibuka lagi |

Membalas tiket yang sudah dijawab mengembalikannya ke **Menunggu jawaban**, jadi jawaban Anda tidak akan tenggelam. Begitu juga membalas tiket yang sudah ditutup — ia terbuka kembali dengan sendirinya.

Anda juga bisa menutup tiket sendiri lewat tombol **Masalah sudah selesai** kalau ternyata sudah beres.

---

## Yang lebih cepat daripada tiket

Beberapa hal punya jawabannya sendiri di dashboard, dan biasanya lebih cepat daripada menunggu balasan:

- **Nomor terputus** — buka menu **Sesi WhatsApp**. Kalau tertulis alasannya di sana, itu jawabannya. Nomor yang dilepas karena tagihan tersambung sendiri setelah pembayaran dikonfirmasi.
- **Pesan berhenti terkirim** — periksa **Langganan** (masa berlaku), atau **Saldo** kalau Anda memakai pay as you go.
- **Pesan berstatus Mengantre lama** — wajar untuk pengiriman massal; kami menahan tiap pesan beberapa detik agar nomor Anda tidak diblokir WhatsApp. Lihat [Praktik Baik](PRAKTIK_BAIK.md).
- **Galat dari API** — kode dan artinya ada di [Referensi API](REFERENSI_API.md).
