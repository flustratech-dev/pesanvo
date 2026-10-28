# Dokumentasi Internal — Pesanvo Gateway

Peta dokumentasi teknis untuk tim. Semua Bahasa Indonesia.

> ## Dua kumpulan dokumentasi, jangan tertukar
>
> **`docs/` (folder ini) — internal.** Arsitektur, referensi kode, referensi
> database, deployment, operasional. **Tidak pernah disajikan lewat web.**
> Isinya cara kerja bagian dalam sistem, termasuk cara memasang source code.
>
> **`resources/docs/` — publik.** Dokumentasi produk yang tampil di
> `/docs` pada website: cara memakai gateway, dan contoh kode integrasi untuk
> developer yang memanggil API kita.
>
> Pelanggan tidak perlu — dan tidak boleh — melihat isi dapur sistem yang
> mereka sewa. Ada tes yang menjaga ini: `DocsTest` menolak frasa seperti
> `git clone` dan `github.com` muncul di halaman publik.
>
> Kalau menambah dokumen baru, tanyakan dulu: **ini untuk siapa?**

---

## Mulai dari mana

**Baru pertama kali mendengar project ini?**
→ [PENGANTAR.md](PENGANTAR.md) — apa itu, untuk apa, masalah apa yang diselesaikan, dan istilah-istilahnya. Tidak ada asumsi pengetahuan sebelumnya.

**Developer baru yang akan menulis kode?**
→ [PENGANTAR.md](PENGANTAR.md) → [ARSITEKTUR.md](ARSITEKTUR.md) → [PANDUAN_DEVELOPER.md](PANDUAN_DEVELOPER.md)

**Cuma mau memanggil API-nya dari aplikasi lain?**
→ [API.md](API.md), atau [INTEGRASI_APP.md](INTEGRASI_APP.md) kalau aplikasinya bagian dari ekosistem Pesanvo

**Mau men-deploy dari VPS kosong?**
→ [SETUP_VPS_COOLIFY.md](SETUP_VPS_COOLIFY.md) — panduan lengkap berurutan

**Sudah punya Coolify, tinggal menambah resource?**
→ [ENVIRONMENT.md](ENVIRONMENT.md) → [DEPLOYMENT.md](DEPLOYMENT.md)

**Ada yang bermasalah di produksi?**
→ [OPERASIONAL.md](OPERASIONAL.md)

**Punya pertanyaan singkat?**
→ [FAQ.md](FAQ.md)

---

## Daftar lengkap

### Memahami

| Dokumen | Isi |
|---|---|
| [PENGANTAR.md](PENGANTAR.md) | Apa & untuk apa, masalah yang diselesaikan, istilah, cara sesi bertahan dari deploy ulang |
| [ARSITEKTUR.md](ARSITEKTUR.md) | Bagaimana bagian-bagiannya bekerja sama, alur data, alasan tiap keputusan, batas yang disengaja |
| [GLOSARIUM.md](GLOSARIUM.md) | Istilah dari A sampai Z |
| [PERBANDINGAN_PROVIDER.md](PERBANDINGAN_PROVIDER.md) | whatsapp-web.js vs WhatsApp Business API resmi (Twilio/Meta), lengkap dengan biayanya |

### Membangun

| Dokumen | Isi |
|---|---|
| [PANDUAN_DEVELOPER.md](PANDUAN_DEVELOPER.md) | Menyiapkan mesin, menjalankan, struktur kode, menambah fitur, menulis tes |
| [REFERENSI_KODE.md](REFERENSI_KODE.md) | Setiap kelas penting di sisi Laravel: tanggung jawab & jebakannya |
| [REFERENSI_DATABASE.md](REFERENSI_DATABASE.md) | Setiap tabel, setiap kolom, dan alasan keberadaannya |
| [ENGINE.md](ENGINE.md) | Seluk-beluk engine Node.js: siklus hidup sesi, antrean, store RemoteAuth |
| [KONTRIBUSI.md](KONTRIBUSI.md) | Alur git, standar kode, daftar periksa sebelum menggabungkan |

### Memakai

| Dokumen | Isi |
|---|---|
| [API.md](API.md) | Referensi REST API v1: setiap endpoint, contoh, kode galat, contoh integrasi |
| [INTEGRASI_APP.md](INTEGRASI_APP.md) | Menyambungkan aplikasi Pesanvo lain ke gateway |
| [VERIFIKASI_NOMOR.md](VERIFIKASI_NOMOR.md) | Alur OTP dan kenapa nomor wajib diverifikasi |
| [AKUN_TERTAUT.md](AKUN_TERTAUT.md) | Satu akun untuk WA Gateway dan pesanvo — protokol, env, dan cara menyalakannya |

### Menjalankan

| Dokumen | Isi |
|---|---|
| [SETUP_VPS_COOLIFY.md](SETUP_VPS_COOLIFY.md) | **Dari VPS kosong sampai gateway melayani permintaan** — pasang Coolify, DNS, database, satu resource, verifikasi |
| [ENVIRONMENT.md](ENVIRONMENT.md) | Tiga tahap: development, staging, production |
| [PINDAH_KE_PESANVO.md](PINDAH_KE_PESANVO.md) | **Pindah dari merek Pesanvo ke Pesanvo** — apa yang berubah, dan urutan aman memindahkan database, domain, dan repo |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Rincian tiap resource Coolify, plus uji regresi wajib |
| [CUTOVER.md](CUTOVER.md) | **Deploy perbaikan kebocoran memori** — checklist sebelum menekan Deploy, lima menit sesudahnya, dan rollback (termasuk satu arah yang tidak bisa dibalik begitu saja) |
| [OPERASIONAL.md](OPERASIONAL.md) | Pemantauan, pemulihan, pemecahan masalah, skala, keamanan |
| [FAQ.md](FAQ.md) | Pertanyaan yang sering muncul |

---

## Tiga hal yang paling sering ditanyakan

**Kenapa dulu harus scan QR setiap deploy, sekarang tidak?**
Kredensial nomor sekarang disimpan di penyimpanan permanen yang hidup terpisah dari container, **dan** dicadangkan ke database tiap 5 menit. Deploy ulang tidak menyentuh keduanya. → [PENGANTAR.md](PENGANTAR.md#5-bagaimana-masalah-scan-ulang-tiap-deploy-diselesaikan)

**Apakah nomor jadi terkunci?**
Tidak. Putus tautan lalu scan lagi dengan nomor mana pun. Yang dijamin justru kebalikannya: nomor tidak terputus sendiri karena deploy. → [FAQ.md](FAQ.md#apakah-nomor-saya-terkunci-bisa-diganti)

**Kenapa broadcast lambat?**
Setiap pesan diberi jeda acak 3–8 detik. Mengirim beruntun tanpa jeda adalah cara tercepat membuat nomor diblokir. → [FAQ.md](FAQ.md#kenapa-broadcast-saya-lambat-sekali)

---

## Dokumentasi publik

Yang tampil di website ada di `resources/docs/`, terdaftar di
`app/Support/DocsRepository.php`:

| Berkas | Halaman |
|---|---|
| `MULAI_CEPAT.md` | Dari mendaftar sampai pesan pertama terkirim |
| `MENAUTKAN_NOMOR.md` | Menghubungkan, memantau, dan mengganti nomor |
| `MENGIRIM_PESAN.md` | Teks, lampiran, pengiriman massal, status |
| `TEMPLATE_PESAN.md` | Template dan placeholder |
| `REFERENSI_API.md` | Setiap endpoint dengan contoh |
| `CONTOH_INTEGRASI.md` | Kode PHP, Node.js, Python |
| `WEBHOOK.md` | Menerima pesan masuk |
| `API_KEY.md` | Membuat dan menjaga kunci akses |
| `PRAKTIK_BAIK.md` | Menghindari pemblokiran nomor |
| `BATAS_DAN_KUOTA.md` | Batas dan apa yang terjadi saat tercapai |
| `FAQ.md`, `GLOSARIUM.md` | Pertanyaan umum dan istilah |

Menambah halaman baru: buat berkasnya di `resources/docs/`, lalu daftarkan di
katalog `DocsRepository`. Katalog itu sekaligus daftar putih — berkas yang
tidak terdaftar tidak bisa dibuka lewat URL.

## Menulis dokumentasi

- **Tentukan sasarannya lebih dulu.** Untuk tim → `docs/`. Untuk pelanggan → `resources/docs/`.
- **Bahasa Indonesia.** Istilah teknis tanpa padanan mapan (queue, webhook, endpoint, timestamp) boleh tetap bahasa Inggris.
- **Jelaskan alasan, bukan hanya mekanisme.** Skema dan kode sudah menunjukkan *apa*; dokumentasi berguna saat menjelaskan *kenapa begitu* dan *apa akibatnya kalau tidak*.
- **Di dokumentasi publik, tulis dari sudut pandang pengguna.** Mereka tidak peduli kelas mana yang menangani apa — mereka ingin tahu tombol mana yang ditekan dan endpoint mana yang dipanggil.
- **Catat batasnya dengan jujur.** Yang belum dibuat lebih baik ditulis daripada dibiarkan ditemukan sendiri.
- **Perbarui bersama kodenya**, dalam commit yang sama.
