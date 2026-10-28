# Mulai Cepat

Dari mendaftar sampai pesan pertama terkirim. Biasanya selesai di bawah lima menit.

---

## 1. Buat akun

Daftar, lalu isi nama workspace Anda — biasanya nama perusahaan atau tim. Workspace adalah wadah untuk nomor WhatsApp, API key, dan riwayat pesan Anda.

Satu akun bisa punya beberapa workspace, dan Anda bisa mengundang rekan tim ke dalamnya.

## 2. Tautkan nomor WhatsApp

Masuk ke menu **Sesi WhatsApp**:

1. Isi nama sesi — bebas, misalnya "CS Utama" atau "Notifikasi Invoice"
2. Klik **Buat sesi**, lalu **Hubungkan**
3. QR code muncul dalam beberapa detik
4. Di HP: buka WhatsApp → **Setelan** → **Perangkat Tertaut** → **Tautkan Perangkat**
5. Scan QR-nya

Status berubah menjadi **Terhubung** dan nomor Anda muncul di kartu sesi.

> **Gunakan nomor yang memang untuk bisnis.** Nomor ini akan mengirim pesan atas nama Anda. Baca [Praktik Baik](PRAKTIK_BAIK.md) sebelum mulai mengirim banyak pesan.

### Nomor bisa diganti kapan saja

Nomor tidak terkunci. Klik **Putus tautan / ganti nomor** pada sesi, lalu **Hubungkan** lagi dan scan dengan nomor lain. Riwayat pesan dan API key tetap utuh.

Yang kami jamin justru sebaliknya: nomor Anda **tidak akan terputus sendiri** hanya karena sistem kami diperbarui.

## 3. Kirim pesan percobaan

Menu **Kirim Pesan**:

1. Pilih sesi pengirim
2. Isi nomor tujuan — format `08…` maupun `62…` sama-sama diterima
3. Tulis pesannya
4. Klik **Kirim**

Lihat hasilnya di **Riwayat Pesan**. Statusnya akan naik dari *Antre* → *Terkirim* → *Sampai*.

## 4. Sambungkan ke aplikasi Anda

Di sinilah gateway ini berguna: aplikasi Anda mengirim WhatsApp tanpa perlu mengurus browser, antrean, atau koneksi.

### Ambil API key

Menu **API Keys** → beri nama (misalnya "Aplikasi Kasir") → **Buat**.

Kunci ditampilkan **satu kali saja**. Salin dan simpan di tempat aman — kami hanya menyimpan sidik jarinya, bukan kuncinya.

### Kirim pesan pertama lewat API

```bash
curl -X POST https://wa.pesanvo.id/api/v1/messages/text \
  -H "X-Api-Key: vwa_xxxxxxxx.xxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'
```

Balasannya:

```json
{
  "success": true,
  "data": {
    "id": "01K2CDEFGH7JKMNPQRSTVWXYZ",
    "to": "6281234567890",
    "status": "queued"
  }
}
```

`status: "queued"` berarti pesan sudah kami terima dan masuk antrean. Simpan `id`-nya untuk melacak status pengiriman.

### Simpan kuncinya di `.env`, bukan di dalam kode

Halaman **API Keys** menampilkan blok ini siap salin begitu kunci dibuat:

```
WA_GATEWAY_URL=https://wa.pesanvo.id
WA_GATEWAY_KEY=vwa_xxxxxxxx.xxxxxxxxxxxx
WA_GATEWAY_SESSION=
```

`WA_GATEWAY_SESSION` sengaja dibiarkan kosong. Pesan akan dikirim dari nomor yang sedang terhubung — Anda tidak perlu menyalin ID sesi ke mana pun.

Isi kolom itu hanya kalau workspace Anda punya beberapa nomor dan satu aplikasi harus selalu mengirim dari salah satunya. ID sesi ada di kartu sesi, tombol **Salin ID**.

---

## Langkah berikutnya

| Kalau Anda ingin… | Baca |
|---|---|
| Memahami cara menautkan dan mengelola nomor | [Menautkan Nomor](MENAUTKAN_NOMOR.md) |
| Mengirim gambar, dokumen, atau ke banyak nomor | [Mengirim Pesan](MENGIRIM_PESAN.md) |
| Menerima balasan pelanggan di aplikasi Anda | [Webhook](WEBHOOK.md) |
| Melihat seluruh endpoint yang tersedia | [Referensi API](REFERENSI_API.md) |
| Contoh kode siap pakai | [Contoh Integrasi](CONTOH_INTEGRASI.md) |
| Menjaga nomor agar tidak diblokir | [Praktik Baik](PRAKTIK_BAIK.md) |
