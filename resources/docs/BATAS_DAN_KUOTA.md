# Batas & Kuota

Batas yang berlaku di workspace Anda, dan apa yang terjadi saat tercapai.

---

## Melihat pemakaian Anda

Menu **Pengaturan** menampilkan batas workspace Anda saat ini beserta riwayat pemakaian per bulan.

Menu **Ringkasan** menampilkan pemakaian bulan berjalan dengan indikator kuota.

Lewat API:

```bash
curl -H "X-Api-Key: $KEY" https://wa.pesanvo.id/api/v1/health
```

```json
{
  "success": true,
  "data": {
    "sessions": { "total": 2, "connected": 1, "limit": 3 },
    "usage": {
      "period": "2026-08",
      "messages_sent": 143,
      "messages_received": 27,
      "messages_failed": 2,
      "quota": 1000
    }
  }
}
```

## Jenis batas

### Jumlah nomor

Berapa banyak nomor WhatsApp yang bisa ditautkan sekaligus, tergantung paket Anda.

Saat tercapai, membuat sesi baru ditolak:

```json
{ "success": false, "error": { "message": "Tenant sudah memakai seluruh jatah sesi (1)." } }
```

Hapus sesi yang tidak dipakai, atau naikkan paket.

### Saldo (pay as you go)

Kalau workspace Anda memakai pay as you go, yang membatasi pengiriman bukan kuota bulanan melainkan **saldo** — dan tidak ada tanggal berakhir sama sekali.

Saat saldo tidak cukup, pengiriman ditolak:

```json
{ "success": false, "error": { "message": "Saldo tidak cukup untuk mengirim pesan. Isi saldo dari menu Saldo di dashboard." } }
```

Yang perlu diingat saat menangani ini di aplikasi Anda:

- **Saldo dipotong saat pesan terkirim, bukan saat diantrekan.** Pesan yang gagal tidak memotong apa pun.
- Pengiriman massal memeriksa saldo dua kali — saat mengantre dan tepat sebelum kirim — jadi sebagian pesan bisa terkirim sementara sisanya gagal kalau saldo habis di tengah jalan. Periksa status tiap pesan, jangan menganggap seluruh batch berhasil.
- Kami mengabari Anda lewat WhatsApp saat saldo menipis, sebelum berhenti terjadi.

Rincian tarif, sisa pesan, dan riwayat mutasi ada di menu **Saldo**. Lihat [Langganan & Tagihan](LANGGANAN_DAN_TAGIHAN.md).

### Kuota pesan bulanan

Jumlah pesan **keluar** per bulan kalender. Pesan masuk tidak dihitung.

Kuota dihitung **saat pesan diantrekan**, bukan saat terkirim. Jadi pengiriman massal 1000 nomor langsung memotong 1000 dari kuota Anda, meski pengirimannya baru selesai sejam kemudian.

Alasannya: kalau dihitung belakangan, Anda bisa terlanjur mengantrekan puluhan ribu pesan sebelum ketahuan melewati batas.

Saat habis:

```json
{ "success": false, "error": { "message": "Kuota pesan bulan ini sudah habis (1000 pesan)." } }
```

Kuota disetel ulang otomatis di awal bulan.

**Pesan gagal tetap terhitung.** Kami sudah melakukan usaha pengirimannya. Kalau angka kegagalan Anda tinggi, periksa daftar nomor tujuan — nomor yang tidak terdaftar di WhatsApp memakan kuota tanpa hasil.

### Batas permintaan API

Berapa banyak permintaan HTTP per menit yang bisa dilakukan satu API key.

Melewatinya menghasilkan `429` beserta header `Retry-After`:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 34
```

Batas ini dihitung **per kunci**, bukan per alamat IP — jadi beberapa aplikasi Anda di server yang sama tidak saling menghabiskan jatah.

Endpoint `/api/v1/health` dikecualikan, aman dipanggil sesering apa pun untuk pemantauan.

**Ini berbeda dari kuota pesan.** Batas permintaan mencegah lonjakan sesaat; kuota membatasi volume bulanan. Satu permintaan `bulk` berisi 500 nomor dihitung sebagai **satu** permintaan tapi **500** pesan.

## Batas teknis WhatsApp

Yang berikut ini berasal dari WhatsApp, bukan dari paket Anda — berlaku sama untuk semua:

| Batas | Nilai |
|---|---|
| Panjang pesan teks | 4096 karakter |
| Ukuran lampiran | 16 MB |
| Panjang caption media | 1024 karakter |
| Nomor per permintaan massal | 1000 (batas kami, demi kestabilan) |

## Retensi data

Riwayat pesan disimpan selama masa retensi paket Anda, lalu dipangkas otomatis. Angka pemakaian bulanan tetap tersimpan permanen.

Kalau Anda perlu menyimpan riwayat lebih lama untuk keperluan audit, salin ke sistem Anda sendiri lewat endpoint `GET /api/v1/messages`, atau terima setiap pesan lewat [webhook](WEBHOOK.md).

## Menangani batas di aplikasi Anda

**Periksa pemakaian sebelum pengiriman besar:**

```php
$sisa = Http::withHeader('X-Api-Key', $key)
    ->get("{$base}/api/v1/health")
    ->json('data.usage');

$tersisa = $sisa['quota'] - $sisa['messages_sent'];

if (count($nomor) > $tersisa) {
    // Beri tahu tim, atau kirim sebagian dulu
}
```

**Tangani `429` dengan menunggu, bukan mengulang segera:**

```php
if ($response->status() === 429) {
    $tunggu = (int) $response->header('Retry-After') ?: 60;
    // jadwalkan ulang setelah $tunggu detik
}
```

**Jangan ulangi `422`.** Kuota habis atau nomor tidak valid tidak akan membaik dengan diulang.

## Menaikkan batas

Kirim tiket dari menu **Bantuan** di dashboard untuk membahas paket yang sesuai dengan volume Anda — lihat [Bantuan & Tiket](BANTUAN.md).

Kalau kebutuhan Anda melampaui paket Elite — lebih banyak nomor, kuota lebih besar, atau retensi lebih panjang — isi formulir **Enterprise** di halaman harga. Batas dan harganya disusun mengikuti kebutuhan Anda.

Sebelum menaikkan, pastikan volume Anda memang wajar untuk jalur ini — baca [Praktik Baik](PRAKTIK_BAIK.md). Volume besar lewat nomor biasa punya risiko tersendiri yang tidak bisa diselesaikan hanya dengan menaikkan kuota.
