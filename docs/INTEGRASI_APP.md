# Menyambungkan Aplikasi Pesanvo ke Gateway

Klien-nya disalin per aplikasi, mengikuti pola yang sudah dipakai `CentralAuthClient` dan `SloNotifier`. Sumber aslinya ada di `docs/client/` repo ini — **ubah di sana dulu, lalu salin ulang**.

## Nomor pengirim: platform vs workspace

| Jenis | Nomor | Dipakai untuk |
|---|---|---|
| **Platform** | Nomor resmi Pesanvo | OTP, undangan anggota, notifikasi billing, balasan tiket — pesan atas nama Pesanvo |
| **Workspace** | Nomor perusahaan sendiri | Invoice ke customer, PO ke vendor, slip gaji — pesan atas nama perusahaan |

Pemisahan ini bukan kerapian belaka. Customer sebuah perusahaan tidak mengenal Pesanvo; invoice dari nomor asing terlihat seperti penipuan. Dan ratusan invoice per hari dari satu nomor platform adalah pola yang paling cepat membuat nomor itu diblokir WhatsApp — kalau itu terjadi, OTP dan seluruh notifikasi Pesanvo ikut mati.

Karena itu, bila perusahaan belum menautkan nomornya sendiri, sistem **tidak** diam-diam memakai nomor platform. Yang terjadi: kembali ke tautan `wa.me` manual seperti sebelumnya.

## Pemasangan

1. Salin dua file:

   ```
   docs/client/config/whatsapp.php          →  <app>/config/whatsapp.php
   docs/client/app/Services/WhatsAppGateway.php  →  <app>/app/Services/WhatsAppGateway.php
   ```

2. Tambahkan ke `.env` aplikasi:

   ```env
   WA_GATEWAY_URL=https://wa.pesanvo.id
   WA_GATEWAY_KEY=vwa_xxxxxxxx.xxxxxxxxxxxxxxxx
   WA_GATEWAY_SESSION=
   WA_CS_GATEWAY_KEY=
   WA_CS_GATEWAY_SESSION=
   ```

   `WA_GATEWAY_SESSION` dibiarkan kosong: pesan dikirim dari sesi yang sedang terhubung di workspace milik kunci itu. Isi hanya kalau workspace punya beberapa nomor dan pengirimnya harus dikunci.

   Dua baris `WA_CS_*` mengisi kanal CS — lihat bagian berikutnya. Dikosongkan berarti kanal CS memakai kredensial di atasnya.

3. Buat API key-nya di dashboard gateway, satu kunci per aplikasi per lingkungan.

## Dua kanal: platform dan CS

Aplikasi yang mengabari **operator Pesanvo sendiri** — bukti pembayaran baru di app-pricing, tiket baru di app-helpdesk — memakai kanal terpisah dari yang berbicara kepada pelanggan.

| Kanal | Kredensial | Nomor pengirim | Dipakai untuk |
|---|---|---|---|
| `CHANNEL_PLATFORM` (bawaan) | `WA_GATEWAY_KEY` | nomor Pesanvo yang dikenal pelanggan | langganan aktif, pengingat invoice, perkembangan tiket, maintenance |
| `CHANNEL_CS` | `WA_CS_GATEWAY_KEY` | nomor CS | bukti pembayaran baru, tiket baru — masuk ke `WA_GATEWAY_ADMIN_PHONE` |

Nomor CS didaftarkan sebagai **workspace tersendiri dengan API key sendiri**, bukan sesi kedua di workspace platform: kuota dan riwayat pesannya jadi tidak bercampur dengan trafik pelanggan, sehingga lonjakan di salah satunya tidak mendiamkan yang lain.

Sebelum ada kanal CS, `WA_GATEWAY_ADMIN_PHONE` diisi nomor gateway itu sendiri sehingga kabar internal mendarat di chat "Pesan ke Diri Sendiri" — satu tumpukan berisi kiriman ke pelanggan dan kabar untuk operator sekaligus. Kalau `WA_CS_GATEWAY_KEY` dikosongkan, perilaku lama itulah yang berlaku: kanal CS jatuh kembali ke kredensial platform, supaya notifikasi operator tidak hilang diam-diam gara-gara satu env belum diisi.

## Cara pakai

```php
use App\Services\WhatsAppGateway;

WhatsAppGateway::send($user->phone, "Halo {$user->name}, ...");
WhatsAppGateway::broadcast($phones, 'Pengumuman');
WhatsAppGateway::template($phone, 'pengingat-invoice', ['nomor' => 'INV-001']);

// Kabar untuk operator Pesanvo sendiri, dikirim dari nomor CS.
WhatsAppGateway::send($adminPhone, 'Bukti pembayaran baru.', channel: WhatsAppGateway::CHANNEL_CS);
```

Semua method mengembalikan `bool` dan **tidak pernah melempar exception**. Notifikasi WhatsApp adalah pelengkap; invoice tetap harus tersimpan meski WhatsApp-nya gagal terkirim.

## Penyaring nomor terverifikasi — sekarang mati

`config('whatsapp.require_verified_phone')` bernilai **`false`** di app-pricing dan app-helpdesk (14 Agu 2026), dan bawaannya di config pun `false`.

Dokumen ini dulu menyatakan sebaliknya: `true`, dan "jangan dimatikan di produksi". Yang tidak diperhitungkan saat itu adalah bahwa **tidak ada apa pun yang pernah mengisi `users.phone_verified_at`**. Kolom itu hanya bisa terisi lewat SSO dari app-auth, mencerminkan claim `phone_verified` — sementara di app-auth tidak ada satu pun tautan atau alur pendaftaran yang mengantar pengguna ke halaman verifikasi nomor (`/account/phone`). Halamannya hidup, tapi tidak terjangkau.

Akibatnya penyaring itu tidak menyaring sebagian, ia menolak **seluruhnya**: setiap notifikasi WhatsApp ke pengguna dilewati diam-diam, tanpa galat dan tanpa jejak di log. Pengaman yang menolak semuanya tanpa suara lebih berbahaya daripada risiko yang seharusnya ia cegah, karena tidak ada yang menyadarinya sampai ada pengguna mengeluh tidak pernah dikabari.

Yang ditanggung sekarang: `users.phone` hanya pernah divalidasi *formatnya*. Salah ketik satu digit berarti pesan mendarat di HP orang asing, lengkap dengan nama pelanggan dan nomor invoice — dan laporan spam dari mereka bisa membuat nomor platform diblokir.

**Nyalakan lagi begitu verifikasi nomor benar-benar dijalani pengguna** — yang dibutuhkan cuma jalur yang mengantar mereka ke halaman yang sudah ada. Lihat [VERIFIKASI_NOMOR.md](VERIFIKASI_NOMOR.md).

## Status per aplikasi

### app-erp (Pesanvo Office)

`App\Services\WhatsAppService::sendMessage()` dipertahankan sebagai pembungkus tipis di atas `WhatsAppGateway`, sehingga seluruh pemanggil lama tidak berubah: `SendInvoiceReminders`, `SendTaskReminders`, `MeetingController`, `TaskController`, `AdminSettingsController`, `NotificationTemplateController`.

Yang dihapus dari repo ERP: `whatsapp-server.js`, `nixpacks.toml` (apt package Chromium), peluncuran Node di `start.sh`, dan dependensi `whatsapp-web.js`/`qrcode`/`express`/`cors` di `package.json`.

Halaman `admin/whatsapp` sekarang menampilkan status ringkas dari gateway dan menautkan ke dashboardnya.

### app-web

Memakai channel notifikasi Laravel. Notification tinggal menambahkan `'whatsapp'` ke `via()` dan menyediakan `toWhatsApp()`:

```php
public function via($notifiable): array
{
    return ['mail', 'database', 'whatsapp'];
}

public function toWhatsApp($notifiable): string
{
    return "Halo {$notifiable->name}, ...";
}
```

Sudah dipasang di: `FamilyInvitationReceived`, `CircleInvitationReceived`, `TeamMemberAddedNotification`, `TeamMemberInvitedWithNewAccountNotification`.

> Catatan pada notifikasi akun baru: **password sementara sengaja tidak ikut dikirim lewat WhatsApp**. Riwayat chat tersinkron ke semua perangkat tertaut dan terlihat di notifikasi layar kunci. Kredensial tetap hanya lewat email; WhatsApp cuma memberi tahu bahwa undangannya ada.

Untuk dokumen bisnis, `BusinessNotificationTriggerService::dispatchToRecipient()` mengirim otomatis dari nomor perusahaan bila `business_companies.wa_auto_send` menyala dan `wa_session_id` terisi. Kalau tidak, hasilnya tetap membawa `whatsapp_url` untuk tombol `wa.me` seperti sebelumnya.

### app-pricing

`App\Services\WhatsAppNotifier` dipanggil berdampingan dengan Mailable yang sudah ada: langganan aktif, langganan akan berakhir, langganan dibatalkan, bukti pembayaran ditolak, pengingat invoice, dan pengumuman maintenance. Admin tetap hanya lewat email.

### app-helpdesk

`App\Services\WhatsAppNotifier` untuk tiket dibuat, tiket diperbarui, tiket selesai, dan pengumuman maintenance. Pelapor tamu (tanpa akun) tidak dapat WhatsApp — mereka tidak pernah dimintai nomor telepon saat membuat tiket.

## Menerima pesan masuk

Daftarkan webhook di dashboard gateway, lalu verifikasi tanda tangannya. Lihat [API.md](API.md#menerima-webhook).
