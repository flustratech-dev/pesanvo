# Template Pesan

Simpan susunan pesan yang sering dipakai, lalu isi bagian yang berubah lewat API.

---

## Kenapa memakai template

Tanpa template, susunan kalimat tertanam di kode aplikasi Anda. Mengubah kata "Yth." menjadi "Halo" berarti mengubah kode dan men-deploy ulang.

Dengan template, susunannya disimpan di sini. Tim non-teknis bisa memperbaikinya lewat dashboard tanpa menyentuh kode sama sekali.

## Membuat template

Menu **Template**:

| Isian | Contoh | Keterangan |
|---|---|---|
| Nama | `Pengingat Invoice` | Untuk tampilan di dashboard |
| Slug | `pengingat-invoice` | Yang dipakai di API |
| Isi | lihat di bawah | Boleh mengandung placeholder |

Contoh isi:

```
Halo {{ nama }},

Faktur *{{ nomor }}* senilai {{ jumlah }} akan jatuh tempo pada {{ tanggal }}.

Silakan lakukan pembayaran sebelum tanggal tersebut. Terima kasih.
```

Bagian di dalam `{{ }}` adalah **placeholder** — nilainya dikirim saat pesan dibuat.

## Memakainya

```bash
curl -X POST https://wa.pesanvo.id/api/v1/messages/template \
  -H "X-Api-Key: $KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "081234567890",
    "template": "pengingat-invoice",
    "variables": {
      "nama": "Budi Santoso",
      "nomor": "INV-2026-001",
      "jumlah": "Rp 1.500.000",
      "tanggal": "20 Agustus 2026"
    }
  }'
```

Hasil yang diterima pelanggan:

> Halo Budi Santoso,
>
> Faktur **INV-2026-001** senilai Rp 1.500.000 akan jatuh tempo pada 20 Agustus 2026.
>
> Silakan lakukan pembayaran sebelum tanggal tersebut. Terima kasih.

## Aturan placeholder

**Nama placeholder** boleh huruf, angka, dan garis bawah: `{{ nama }}`, `{{ nomor_invoice }}`, `{{ total2 }}`.

**Spasi di dalam kurung diabaikan.** `{{nama}}` dan `{{ nama }}` sama saja.

**Placeholder yang tidak diberi nilai dibiarkan apa adanya.** Kalau Anda lupa mengirim `tanggal`, pesan akan memuat tulisan `{{ tanggal }}` secara harfiah.

Ini disengaja. Kesalahan yang terlihat jelas lebih baik daripada kalimat yang diam-diam bolong — Anda langsung tahu ada yang perlu diperbaiki, alih-alih pelanggan menerima "jatuh tempo pada ." tanpa tanggal.

**Format angka dan tanggal dilakukan di aplikasi Anda.** Kirim `"Rp 1.500.000"`, bukan `1500000`. Gateway tidak mengubah format apa pun.

## Menonaktifkan template

Hilangkan centang **Aktif**. Template yang tidak aktif ditolak dengan galat `404` saat dipanggil, tapi tidak terhapus — berguna untuk menghentikan sementara satu jenis pesan tanpa kehilangan susunannya.

## Menyusun pesan yang baik

**Sebutkan identitas Anda di awal.** Penerima belum tentu menyimpan nomor Anda. Tanpa penjelasan, pesan dari nomor asing mudah dikira penipuan.

```
*Toko Makmur*

Halo {{ nama }}, pesanan Anda sudah dikirim…
```

**Sebutkan alasan pesan itu dikirim.** "Terkait pesanan #1234 yang Anda buat kemarin" jauh lebih menenangkan daripada langsung menagih.

**Jangan sertakan kata sandi, kode OTP, atau data kartu.** Riwayat WhatsApp tersinkron ke semua perangkat tertaut, tercadangkan ke cloud, dan sering terlihat di notifikasi layar kunci.

**Buat sependek mungkin.** Pesan panjang terpotong di daftar chat dan jarang dibaca tuntas.

**Sediakan cara berhenti** untuk pesan promosi. "Balas STOP untuk berhenti menerima info promo." Ini menurunkan kemungkinan penerima menandai Anda sebagai spam — hal yang paling cepat membuat nomor diblokir.
