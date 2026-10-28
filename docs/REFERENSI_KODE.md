# Referensi Kode

Peta setiap kelas penting di sisi Laravel: apa tanggung jawabnya, apa yang boleh dan tidak boleh dilakukan padanya. Untuk sisi Node lihat [ENGINE.md](ENGINE.md).

---

## Aturan yang berlaku di mana-mana

Empat hal ini berulang di seluruh kode. Melanggarnya menimbulkan bug yang sulit dilacak.

### 1. Selalu berangkat dari workspace

```php
// Benar
$session = EnsureWorkspaceSelected::from($request)->sessions()->findOrFail($id);

// Salah — menemukan sesi milik siapa pun
$session = WaSession::find($id);
```

Ini satu-satunya penjaga isolasi antar pelanggan. Tidak ada global scope yang menangkap kelalaian ini, jadi disiplinnya harus di tangan penulis kode.

### 2. Pesan keluar hanya lewat MessageDispatcher

Jangan pernah `Message::create()` langsung untuk pesan keluar. `MessageDispatcher::queue()` yang mengurus normalisasi nomor, pemeriksaan workspace aktif, pemeriksaan kuota, pencatatan pemakaian, dan pengantrean sekaligus.

### 3. Nomor selalu lewat PhoneNumber

`PhoneNumber::normalize()` sebelum menyimpan, `PhoneNumber::mask()` sebelum menulis ke log.

### 4. Kegagalan WhatsApp tidak boleh menjatuhkan proses lain

Notifikasi adalah pelengkap. Invoice tetap harus tersimpan meski WhatsApp-nya gagal terkirim.

---

## Models

### `User`

Akun dashboard. Autentikasi lokal — pesanvo punya form login dan register sendiri.

| Method | Kegunaan |
|---|---|
| `workspaces()` | BelongsToMany lewat `workspace_members`, membawa pivot `role` |
| `roleIn(Workspace)` | Peran di workspace tertentu, `null` bila bukan anggota |
| `canManage(Workspace)` | `true` untuk super admin, owner, atau admin |

`canManage()` dipakai sebelum tindakan yang mengubah pengaturan. Ia membaca dari koleksi `workspaces` yang sudah dimuat, jadi panggil `$user->load('workspaces')` bila akan dipakai berulang dalam satu permintaan.

### `Workspace`

Batas isolasi. Semua relasi data menggantung di sini.

| Method | Kegunaan |
|---|---|
| `currentUsage()` | Baris `usage_counters` bulan ini, dibuat bila belum ada |
| `hasQuotaRemaining()` | `true` untuk workspace internal atau kuota `0` |
| `canAddSession()` | Membandingkan jumlah sesi dengan `max_sessions` |
| `isActive()` | `status === 'active'` |

`currentUsage()` menulis nilai awal `0` secara eksplisit. Baris yang baru dibuat tidak membaca ulang nilai default dari database — tanpa itu, counter terbaca `null`, API melaporkan `null` alih-alih `0`, dan pemeriksaan kuota jadi bergantung pada cara PHP membandingkan `null` dengan angka.

### `WaSession`

| Method | Kegunaan |
|---|---|
| `isConnected()` | |
| `hasFreshQr()` | Ada payload **dan** belum kedaluwarsa |
| `latestBackup()` | Cadangan terakhir |

Memakai `HasUlids` dan `SoftDeletes`. `qr_payload` masuk `$hidden` — ia berukuran ~7 KB dan tidak berguna di respons JSON umum.

### `Message`

`advanceStatus(string $status): bool` adalah bagian terpentingnya. Status hanya boleh naik menurut peringkat `queued(0) → sending(1) → sent(2) → delivered(3) → read(4)`; `failed` di luar peringkat dan selalu diterima. Mengembalikan `false` bila status ditolak — pemanggil memakai itu untuk memutuskan apakah perlu menyimpan.

Ack dari WhatsApp bisa datang tidak berurutan. Tanpa penjagaan ini, pesan yang sudah dibaca bisa turun statusnya jadi sekadar terkirim.

### `ApiKey`

| Method | Kegunaan |
|---|---|
| `ApiKey::issue(Workspace, name, scopes, createdBy)` | Static. Mengembalikan `[model, kunciPolos]` |
| `isUsable()` | Belum dicabut dan belum kedaluwarsa |
| `allows(string $scope)` | `true` bila punya scope itu atau `*` |

Kunci polos **hanya ada di memori sekali**, saat `issue()`. Setelah itu hanya hash yang tersimpan.

### `MessageTemplate`

| Method | Kegunaan |
|---|---|
| `render(array $values)` | Mengganti `{{ placeholder }}` |
| `MessageTemplate::extractVariables(string)` | Static. Daftar placeholder yang dipakai |

Placeholder tanpa pasangan dibiarkan apa adanya — kesalahan yang terlihat lebih baik daripada kalimat yang diam-diam bolong.

### `Webhook`, `WebhookDelivery`, `UsageCounter`, `SessionBackup`, `OtpCode`, `AuditLog`

Sebagian besar model data biasa. Dua yang punya perilaku:

- `Webhook::listensTo(string $event)` — `events` bernilai `null` berarti berlangganan semua.
- `AuditLog::record(action, subject, context, workspaceId)` — static, membaca user dan IP dari permintaan yang sedang berjalan.

---

## Services

### `MessageDispatcher`

Satu-satunya pintu pembuatan pesan keluar.

```php
$dispatcher->queue($session, '081234567890', [
    'type' => 'text',
    'body' => 'Halo',
]);

$dispatcher->queueBulk($session, ['0812…', '0813…'], ['body' => 'Pengumuman']);
```

`queue()` melempar `RuntimeException` bila workspace nonaktif, kuota habis, atau nomor tidak valid.

`queueBulk()` **tidak** melempar untuk nomor yang rusak — ia mengumpulkannya di `rejected` dan melanjutkan sisanya. Satu nomor salah ketik tidak boleh membatalkan broadcast 500 nomor.

`incrementUsage()` memakai upsert lalu `increment()` mentah, aman dari race antar worker.

### `SessionService`

Siklus hidup sesi: `create`, `connect`, `disconnect`, `logout`, `syncStatus`.

Beda `disconnect` dan `logout` menentukan:

- **`disconnect`** — menghentikan proses di engine. Kredensial tetap ada, menyambung lagi tidak perlu QR.
- **`logout`** — memutus tautan perangkat di sisi WhatsApp dan membuang kredensial di kedua sisi. Menyambung lagi berarti scan QR baru, dan **boleh dengan nomor yang berbeda**.

`logout` inilah jalur "ganti nomor". Riwayat pesan, API key, dan webhook tetap utuh karena semuanya menggantung di baris sesi, bukan di kredensialnya.

### `WebhookDispatcher`

Menyiarkan satu event ke semua webhook aktif yang berlangganan. Selalu lewat antrean — endpoint workspace yang lambat tidak boleh menahan permintaan yang sedang berjalan.

Konstanta event: `EVENT_MESSAGE_RECEIVED`, `EVENT_MESSAGE_STATUS`, `EVENT_SESSION_STATUS`, `EVENT_SESSION_QR`.

### `OtpService`

`send()` dan `verify()`. Mengirim dari sesi terhubung milik workspace pemanggil, dengan aturan pemilihan yang sama persis dengan pesan biasa, dan menyebut nama workspace itu di teks kodenya. Batas laju melempar `OtpRateLimited` (dijawab 429); kegagalan lain melempar `RuntimeException` biasa (dijawab 422).

Pembatasan berlapis: jeda kirim ulang, batas harian per nomor, batas percobaan salah, masa berlaku. Kode disimpan ter-hash; kode lama untuk tujuan sama dimatikan setiap kode baru dibuat.

Melempar `RuntimeException` dengan pesan yang aman ditampilkan ke pengguna.

### `Providers\WhatsAppProvider` dan turunannya

```php
interface WhatsAppProvider
{
    public function startSession(WaSession $session): void;
    public function stopSession(WaSession $session): void;
    public function logoutSession(WaSession $session): void;
    public function status(WaSession $session): array;
    public function send(WaSession $session, Message $message): array;
}
```

| Kelas | Keadaan |
|---|---|
| `WwebjsProvider` | Satu-satunya implementasi. Klien HTTP tipis ke engine |
| `ProviderManager` | Memetakan `wa_sessions.driver` ke implementasi; hanya mengenal `wwebjs` |

`ProviderException` membedakan kegagalan sementara dari permanen lewat properti `retryable`. `WwebjsProvider` memetakan HTTP 422 dari engine menjadi permanen, selain itu sementara.

Pembedaan ini menentukan: kegagalan permanen (nomor tidak terdaftar) langsung ditandai gagal, sementara kegagalan sementara (sesi sedang putus) dikembalikan ke antrean.

---

## Jobs

### `SendMessageJob`

`tries = 4`, backoff `[10, 60, 300]` detik.

Backoff naik bertahap karena sesi yang baru putus biasanya butuh puluhan detik untuk tersambung lagi — mengulang secepat mungkin hanya membakar jatah percobaan.

Job berhenti lebih awal bila pesan sudah tidak `queued`: sudah terkirim, dibatalkan, atau dipangkas retensi.

### `DeliverWebhookJob`

`tries = 3`, backoff `[30, 300]` detik.

Menandatangani body dengan secret webhook, mencatat setiap percobaan ke `webhook_deliveries`, dan menonaktifkan webhook setelah 50 kegagalan berturut-turut.

Body ditandatangani **sebelum** dikirim dan dikirim dengan `withBody()`, bukan `post($array)` — supaya byte yang ditandatangani sama persis dengan byte yang diterima penerima. Kalau Laravel meng-encode ulang array-nya, tanda tangan bisa meleset.

### `SyncSessionStatusJob`

Berjalan tiap menit. Jaring pengaman untuk kasus engine mati mendadak tanpa sempat mengirim event `disconnected`.

Menanyakan keadaan sebenarnya ke engine, memperbarui database, dan menyambungkan ulang sesi yang `disconnected` dengan `auto_reconnect` menyala. Kegagalan satu sesi tidak menghentikan pemeriksaan sesi lain.

### `PruneOldRecordsJob`

Harian, 03:15. Menghapus pesan lama beserta file medianya, log kiriman webhook, dan OTP kedaluwarsa.

Media dihapus lewat `chunkById` sebelum barisnya dihapus — kalau barisnya lebih dulu hilang, file medianya jadi yatim dan memenuhi disk selamanya.

---

## Middleware

### `AuthenticateApiKey`

Menerima kunci dari `X-Api-Key` atau `Authorization: Bearer`.

Alurnya: pisah prefix dan rahasia → cari baris berdasarkan prefix → verifikasi hash → cek belum dicabut → cek workspace aktif → cek scope → taruh `api_key` dan `workspace` di atribut permintaan.

`last_used_at` ditulis paling sering sekali per menit (dijaga cache). Menulis di setiap permintaan berarti satu UPDATE per pesan terkirim, padahal kolom itu hanya untuk informasi di dashboard.

Dipakai dengan parameter untuk memeriksa scope: `->middleware('apikey:otp')`.

### `VerifyEngineSignature`

Menjaga seluruh `/internal/*`. Memeriksa timestamp (toleransi 5 menit) lalu HMAC.

Punya dua mode:
- Bawaan: menandatangani `timestamp + "." + body`
- Bila header `X-Engine-Body-Sha256` ada: menandatangani `timestamp + "." + path + "." + sha256` — dipakai untuk unggahan cadangan sesi yang bisa puluhan MB

### `EnsureWorkspaceSelected`

Menentukan workspace yang sedang dibuka, menaruhnya di permintaan, dan membagikannya ke view sebagai `$currentWorkspace` dan `$availableWorkspaces`. Mengarahkan ke onboarding bila pengguna belum punya workspace.

`EnsureWorkspaceSelected::from($request)` adalah cara baku mengambilnya di controller.

---

## Controllers

### `Api\*`

Semua mewarisi `ApiController` yang menyediakan `workspace()`, `apiKey()`, `ok()`, `fail()`. Bentuk respons dijaga seragam: `{success, data}` atau `{success, error}`.

| Controller | Endpoint |
|---|---|
| `HealthController` | `GET /health` — di luar rate limit, aman untuk monitoring |
| `SessionController` | CRUD sesi, connect/disconnect/logout, QR |
| `MessageController` | text, media, bulk, template, riwayat |
| `WebhookController` | daftar, tambah, hapus |
| `OtpController` | kirim, verifikasi — butuh scope `otp` |

`MessageController::resolveSession()` memilih sesi bila pemanggil tidak menyebutkannya: sesi terhubung tertua di workspace itu. Sengaja tanpa penyaringan lain — penyaringan yang tidak terlihat di dashboard pernah membuat sesi hijau tetap ditolak tanpa petunjuk.

### `Internal\*`

Hanya dipanggil engine. Bukan bagian API publik.

| Controller | Kegunaan |
|---|---|
| `BootstrapController` | Daftar sesi yang harus dijalankan saat engine boot |
| `EngineEventController` | Semua event: qr, authenticated, ready, disconnected, auth_failure, message, message_ack |
| `SessionBackupController` | Simpan, ambil, cek, hapus cadangan sesi |

`EngineEventController` membalas `action: stop_session` untuk sesi yang sudah dihapus — engine memakainya untuk mematikan sesi yatim, alih-alih mengulang callback selamanya.

`SessionBackupController::store()` menulis lewat file sementara secara streaming, memvalidasi JSON dan mencocokkan sha256 sebelum menyimpan permanen. Payload session.json multi-file auth diverifikasi secara streaming dan aman dari lonjakan memori PHP.

### `Dashboard\*`

Blade biasa. Semua mengambil workspace lewat `EnsureWorkspaceSelected::from()`.

`SessionController::status()` adalah endpoint JSON yang dipanggil modal QR tiap 3 detik. Polling dipilih daripada websocket: satu permintaan kecil tiap 3 detik hanya selama modal terbuka jauh lebih murah daripada menjalankan Reverb khusus untuk satu kasus pakai.

### `Auth\AuthController`

Login, register, logout lokal.

Register sekalian membuat workspace pertama — tanpa itu pengguna baru mendarat di dashboard yang belum bisa dipakai apa-apa.

Pesan galat login sengaja sama untuk email tidak dikenal maupun password salah, supaya form ini tidak bisa dipakai memetakan email mana yang terdaftar.

---

## Support & Console

### `Support\PhoneNumber`

| Method | Kegunaan |
|---|---|
| `normalize(?string)` | Ke `62812…`, atau `null` bila tidak valid |
| `toChatId(string)` | Menambah `@c.us`; id grup dilewatkan apa adanya |
| `mask(?string)` | `6281****7890` untuk log |

Aturannya sengaja identik dengan `App\Support\WhatsAppLink` di app-web, supaya nomor yang sudah tersimpan di aplikasi lain tidak berubah arti.

Validasinya `62` + 9–13 digit. Lebih pendek dari itu pasti bukan nomor seluler, dan mengirim ke nomor sampah memancing laporan spam.

### `Console\Commands\*`

Kosong. `SetupTenantCommand` dihapus 13 Agustus 2026 — ia membuat workspace tanpa anggota, yang mustahil dibuka lewat dashboard oleh siapa pun. Seluruh penyiapan sekarang lewat dashboard, termasuk untuk Pesanvo sendiri.

---

## Menambah sesuatu

### Endpoint API baru

1. Method di controller `Api\` yang mewarisi `ApiController`
2. Rute di `routes/api.php` dalam grup `v1` + `apikey`
3. **Beri nama berawalan `api.`** bila memakai `apiResource` — nama tanpa awalan akan menimpa rute dashboard dengan nama sama
4. Balas dengan `ok()` / `fail()`
5. Tes di `tests/Feature/`
6. Dokumentasikan di [API.md](API.md)

### Event engine baru

1. Listener di `engine/src/session-manager.js` yang memanggil `laravel.event(...)`
2. Cabang `match` di `EngineEventController`
3. Tes dengan payload bertanda tangan — lihat pola `postEvent()` di `EngineCallbackTest`

### Driver provider baru

1. Implementasi `WhatsAppProvider`
2. Daftarkan di `ProviderManager::driver()`
3. Tambahkan nilainya ke enum `driver` di migrasi `wa_sessions` (migrasi baru)
4. Pakai `ProviderException::permanent()` untuk kegagalan yang tidak akan membaik
