<?php

namespace App\Services\Billing;

use App\Mail\PembayaranDiterima;
use App\Mail\TagihanTerbit;
use App\Models\AuditLog;
use App\Models\EnterprisePlan;
use App\Models\Invoice;
use App\Models\ReferralCode;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\Workspace;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\WhatsAppNotifier;
use App\Services\SessionService;
use App\Support\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Satu-satunya tempat status langganan berpindah.
 *
 * Setiap perpindahan menyentuh dua tabel sekaligus — `subscriptions.status`
 * yang dibaca halaman tagihan, dan `workspaces.status` yang dibaca
 * `MessageDispatcher` serta middleware API. Keduanya harus selalu sepakat:
 * langganan yang lewat jatuh tempo tapi workspace-nya masih `active` berarti
 * pelanggan tetap bisa mengirim tanpa membayar, dan sebaliknya berarti layanan
 * mati padahal tagihannya lunas.
 *
 * Karena itu tidak ada satu pun controller, job, atau perintah yang boleh
 * menulis `subscriptions.status` sendiri.
 */
class SubscriptionService
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly WhatsAppNotifier $notifier,
        private readonly EmailNotifier $email,
        private readonly ReferralService $referrals,
        private readonly BalanceService $balances,
        private readonly Notifier $notifikasi,
    ) {}

    /**
     * Langganan workspace, dibuatkan kalau belum ada.
     *
     * Workspace baru mulai sebagai `unpaid`: belum pernah berlangganan, belum
     * boleh mengirim apa pun. Ia harus memilih paket dan membayar lebih dulu.
     *
     * Masa gratis sampai akhir bulan **hanya** milik akun yang sudah ada
     * sebelum produk ini dijual, dan itu diberikan sekali oleh migrasi
     * `give_existing_workspaces_a_trial` — bukan oleh keadaan bawaan di sini.
     * Kalau bawaannya `trialing`, setiap pendaftar baru ikut memakai produk
     * berbayar secara cuma-cuma tanpa pernah diputuskan siapa pun.
     *
     * Kalau suatu saat masa coba untuk pendaftar baru memang diinginkan,
     * tempatnya di sini — dengan tanggal berakhir yang eksplisit, bukan dengan
     * mengembalikan `trialing` sebagai bawaan.
     */
    public function ensureFor(Workspace $workspace): Subscription
    {
        if ($workspace->subscription) {
            return $workspace->subscription;
        }

        $gratis = Plan::free();

        /*
         | Jatah coba gratis diberikan sekali per PEMILIK, bukan per workspace.
         |
         | Tidak ada batas berapa workspace yang boleh dibuat satu akun — dan
         | itu memang disengaja, satu orang dengan tiga cabang membuat tiga
         | workspace dan membayar tiga kali. Tapi kalau tiap workspace baru ikut
         | membawa masa cobanya sendiri, akun yang sama tinggal menekan
         | "Workspace Baru" sepuluh kali untuk mendapat sepuluh kali lima pesan
         | gratis — masing-masing menahan satu sesi WhatsApp, dari tiga yang
         | tersedia untuk SELURUH pelanggan. Kapasitas engine habis oleh orang
         | yang belum membayar sepeser pun, dan yang tertahan di luar justru
         | pelanggan berbayar.
         |
         | Workspace kedua dan seterusnya lahir `unpaid`: terlihat penuh, bisa
         | disiapkan, tapi baru hidup setelah tagihannya lunas.
        */
        if ($this->pemilikSudahPernahCoba($workspace, $gratis)) {
            $subscription = $workspace->subscription()->create([
                'plan_slug' => config('plans.default'),
                'period' => 'monthly',
                'status' => 'unpaid',
                'current_period_start' => null,
                'current_period_end' => null,
            ]);

            $workspace->forceFill([
                'status' => 'suspended',
                'service_until' => null,
            ])->save();

            return $workspace->setRelation('subscription', $subscription)->subscription;
        }

        $subscription = $workspace->subscription()->create([
            'plan_slug' => $gratis->slug,
            'period' => 'monthly',
            'status' => 'trialing',
            'current_period_start' => now(),
            'current_period_end' => null,
        ]);

        /*
         | Workspace-nya HIDUP, dan itu memang maunya: pendaftar baru boleh
         | menautkan nomor, membuat API key, dan menguji integrasinya. Yang
         | membatasi bukan status melainkan jatah lima pesan — dihitung seumur
         | hidup workspace di `Workspace::freeMessagesUsed()`, bukan per bulan.
         |
         | `service_until` sengaja dibiarkan kosong: masa coba ini tidak punya
         | tanggal berakhir, ia berakhir saat pesan kelima terkirim.
        */
        $this->applyPlanLimits($workspace, $gratis);

        $workspace->forceFill([
            'status' => 'active',
            'service_until' => null,
        ])->save();

        return $workspace->setRelation('subscription', $subscription)->subscription;
    }

    /**
     * Menerbitkan tagihan. Mengembalikan tagihan yang sudah ada kalau workspace
     * masih punya satu yang menunggu bayar untuk paket dan periode yang sama —
     * mengklik "Bayar" dua kali tidak boleh menghasilkan dua nomor tagihan,
     * karena keduanya akan punya kode unik berbeda dan pelanggan akan membayar
     * salah satunya sementara yang lain menggantung sampai kedaluwarsa.
     */
    public function issueInvoice(
        Workspace $workspace,
        string $planSlug,
        string $period,
        ?ReferralCode $referral = null,
    ): Invoice {
        if (! Plan::exists($planSlug)) {
            throw new RuntimeException("Paket '{$planSlug}' tidak ada.");
        }

        if (! in_array($period, ['monthly', 'yearly'], true)) {
            throw new RuntimeException("Periode '{$period}' tidak dikenali.");
        }

        $subscription = $this->ensureFor($workspace);

        $existing = $workspace->invoices()
            ->where('status', 'pending')
            ->where('plan_slug', $planSlug)
            ->where('period', $period)
            ->where('due_at', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $plan = Plan::get($planSlug);
        $amount = $plan->price($period);
        $tax = (int) round($amount * config('billing.tax_percent') / 100);

        /*
         | Dua potongan, dan URUTANNYA menentukan angkanya.
         |
         | Promo perkenalan dipotong lebih dulu, lalu kode referal memotong
         | SISANYA — bukan keduanya dari harga normal. Keputusan Owner (7 Sep
         | 2026): boleh ditumpuk. Menumpuknya dari harga normal akan membuat
         | dua potongan 47% dan 10% berjumlah 57%, sementara yang dimaksud
         | adalah 10% dari yang tersisa sesudah promo.
         |
         | Keduanya dihitung SEBELUM kode unik, dan itu bukan selera:
         | `allocateUniqueCode()` menjamin nominal AKHIR unik di antara tagihan
         | terbuka. Kalau potongan diambil sesudahnya, yang dijamin unik adalah
         | nominal sebelum potongan — dan yang benar-benar ditransfer pelanggan
         | bisa bertabrakan dengan tagihan orang lain, persis hal yang kode unik
         | ini ada untuk mencegahnya.
        */
        $intro = $workspace->berhakHargaPerkenalan($planSlug, $period)
            ? $amount - $plan->introPrice($period)
            : 0;

        $sesudahIntro = $amount + $tax - $intro;
        $diskonReferal = $referral?->potongan($sesudahIntro) ?? 0;

        // `discount_amount` adalah TOTAL kedua potongan, bukan cuma referal:
        // kueri kode unik membacanya sebagai satu angka, dan memecahnya di sana
        // berarti mengubah SQL mentah yang sudah sekali lolos SQLite lalu
        // jatuh di MySQL. `intro_discount_amount` hanya rinciannya.
        $diskon = $intro + $diskonReferal;

        $invoice = DB::transaction(function () use ($workspace, $subscription, $planSlug, $period, $amount, $tax, $diskon, $intro, $referral) {
            $code = $this->allocateUniqueCode($amount + $tax - $diskon);

            $invoice = Invoice::create([
                // Diisi sementara lalu ditimpa: kolomnya unik dan wajib, tapi
                // nomor final diturunkan dari id yang baru ada setelah insert.
                'number' => 'INV-WA-'.Str::ulid(),
                'external_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'plan_slug' => $planSlug,
                'period' => $period,
                'amount' => $amount,
                'tax_amount' => $tax,
                'discount_amount' => $diskon,
                'intro_discount_amount' => $intro,
                'referral_code_id' => $referral?->id,
                'unique_code' => $code,
                'total' => $amount + $tax - $diskon + $code,
                'status' => 'pending',
                'channel' => 'qris_manual',
                'due_at' => now()->addDays(config('billing.invoice_due_days')),
            ]);

            $invoice->forceFill([
                'number' => 'INV-WA-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            // Id workspace disebut eksplisit: penerbitan tagihan juga terjadi
            // dari job harian, dan di sana tidak ada sesi yang bisa ditanya
            // workspace mana yang sedang dibuka.
            AuditLog::record('invoice.issued', $invoice, [
                'plan' => $planSlug,
                'period' => $period,
                'total' => $invoice->total,
                'diskon' => $diskon,
                'promo_perkenalan' => $intro,
                'referral' => $referral?->code,
            ], $workspace->id);

            // Di dalam transaksi bersama tagihannya: penukaran yang tercatat
            // tanpa tagihan, atau tagihan berdiskon tanpa penukaran, keduanya
            // menghasilkan komisi yang tidak bisa dipertanggungjawabkan.
            if ($referral) {
                $this->referrals->catat($referral, $invoice);
            }

            return $invoice;
        });

        /*
         | Di luar transaksi, sama seperti pemberitahuan lunas.
         |
         | Tagihan yang batal tercatat gara-gara SMTP sedang tersendat jauh
         | lebih buruk daripada tagihan yang terbit tanpa suratnya: yang kedua
         | masih terlihat di dashboard dan masih bisa dibayar.
         |
         | Ini satu-satunya peristiwa penagihan yang sengaja TIDAK punya
         | pasangan WhatsApp. Tagihan perpanjangan terbit tiga hari sebelum
         | masa berlaku habis — hari yang sama pengingat H-3 dikirim — dan dua
         | pesan WhatsApp beruntun tentang uang yang sama terbaca seperti
         | penagihan ganda.
        */
        $this->email->toWorkspace(
            $workspace,
            new TagihanTerbit($invoice),
            "invoice-issued:{$invoice->id}",
        );

        $this->notifikasi->keWorkspace(
            workspace: $workspace,
            type: 'invoice.issued',
            title: "Tagihan {$invoice->number} terbit",
            body: 'Rp '.number_format($invoice->total, 0, ',', '.').' — jatuh tempo '
                .$invoice->due_at?->translatedFormat('j F Y').'.',
            url: route('billing.invoice', $invoice->id),
            dedupe: "invoice-issued:{$invoice->id}",
        );

        return $invoice;
    }

    /**
     * Menerbitkan tagihan isi saldo.
     *
     * Sengaja memakai JALUR TAGIHAN YANG SUDAH ADA — QRIS, unggah bukti, admin
     * menandai lunas — bukan alur pembayaran kedua. Alur pembayaran kedua
     * berarti dua tempat yang harus dijaga tetap benar, dua tempat yang bisa
     * kehilangan uang pelanggan, dan dua tempat yang harus dipahami admin yang
     * sedang memverifikasi.
     *
     * Yang membedakannya dari tagihan langganan cuma `plan_slug = 'payg'`, dan
     * itulah yang dibaca `Invoice::isTopup()` saat `markPaid()` bercabang.
     *
     * Jumlahnya bebas di atas minimum, jadi TIDAK ada pemeriksaan tagihan
     * pending yang sama seperti di `issueInvoice()`: pelanggan boleh saja
     * mengisi saldo dua kali berturut-turut, dan dua tagihan topup yang sama
     * nominalnya tetap dibedakan kode uniknya.
     */
    public function issueTopupInvoice(Workspace $workspace, int $jumlah): Invoice
    {
        $minimum = (int) config('billing.payg.min_topup');

        if ($jumlah < $minimum) {
            throw new RuntimeException('Minimum isi saldo Rp '.number_format($minimum, 0, ',', '.').'.');
        }

        $subscription = $this->ensureFor($workspace);
        $tax = (int) round($jumlah * config('billing.tax_percent') / 100);

        $invoice = DB::transaction(function () use ($workspace, $subscription, $jumlah, $tax) {
            $code = $this->allocateUniqueCode($jumlah + $tax);

            $invoice = Invoice::create([
                'number' => 'INV-WA-'.Str::ulid(),
                'external_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'plan_slug' => 'payg',
                'period' => 'monthly',
                // `amount` adalah saldo yang akan diterima pelanggan. Kode unik
                // dan pajak menempel di `total`, TIDAK ikut jadi saldo — kalau
                // ikut, saldo bertambah sebesar angka yang tidak pernah
                // dijanjikan ke siapa pun dan buku besarnya tidak bisa
                // dijelaskan.
                'amount' => $jumlah,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'unique_code' => $code,
                'total' => $jumlah + $tax + $code,
                'status' => 'pending',
                'channel' => 'qris_manual',
                'due_at' => now()->addDays(config('billing.invoice_due_days')),
            ]);

            $invoice->forceFill([
                'number' => 'INV-WA-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            AuditLog::record('invoice.issued', $invoice, [
                'jenis' => 'topup',
                'saldo' => $jumlah,
                'total' => $invoice->total,
            ], $workspace->id);

            return $invoice;
        });

        $this->email->toWorkspace($workspace, new TagihanTerbit($invoice), "invoice-issued:{$invoice->id}");

        return $invoice;
    }

    /**
     * Menerbitkan tagihan untuk kesepakatan Enterprise.
     *
     * Memakai JALUR TAGIHAN YANG SUDAH ADA — QRIS, unggah bukti, admin menandai
     * lunas — persis seperti isi saldo. Tidak ada alur pembayaran ketiga di
     * produk ini; yang membedakannya cuma dari mana harga dan batasnya datang.
     *
     * Harga perkenalan dan kode referal sengaja TIDAK berlaku di sini: harga
     * Enterprise sudah hasil negosiasi, dan memotongnya lagi berarti angka yang
     * disepakati dengan pelanggan bukan angka yang ditagihkan.
     */
    public function issueEnterpriseInvoice(EnterprisePlan $custom, string $period): Invoice
    {
        if (! in_array($period, ['monthly', 'yearly'], true)) {
            throw new RuntimeException("Periode '{$period}' tidak dikenali.");
        }

        $workspace = $custom->workspace;

        if (! $workspace) {
            throw new RuntimeException('Kesepakatan ini tidak menunjuk workspace mana pun.');
        }

        if (! $custom->is_active) {
            throw new RuntimeException('Kesepakatan ini sudah tidak berlaku.');
        }

        $subscription = $this->ensureFor($workspace);
        $amount = $custom->price($period);

        if ($amount <= 0) {
            throw new RuntimeException('Harga kesepakatan belum diisi.');
        }

        $tax = (int) round($amount * config('billing.tax_percent') / 100);

        $invoice = DB::transaction(function () use ($workspace, $subscription, $custom, $period, $amount, $tax) {
            $code = $this->allocateUniqueCode($amount + $tax);

            $invoice = Invoice::create([
                'number' => 'INV-WA-'.Str::ulid(),
                'external_id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'subscription_id' => $subscription->id,
                'plan_slug' => 'enterprise',
                'period' => $period,
                'amount' => $amount,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'intro_discount_amount' => 0,
                'unique_code' => $code,
                'total' => $amount + $tax + $code,
                'status' => 'pending',
                'channel' => 'qris_manual',
                'due_at' => now()->addDays(config('billing.invoice_due_days')),
            ]);

            $invoice->forceFill([
                'number' => 'INV-WA-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            AuditLog::record('invoice.issued', $invoice, [
                'jenis' => 'enterprise',
                'kesepakatan' => $custom->name,
                'periode' => $period,
                'total' => $invoice->total,
            ], $workspace->id);

            return $invoice;
        });

        $this->email->toWorkspace($workspace, new TagihanTerbit($invoice), "invoice-issued:{$invoice->id}");

        return $invoice;
    }

    /**
     * Memindahkan workspace ke pay as you go.
     *
     * `service_until` dan `current_period_end` dikosongkan: yang membatasi
     * workspace PAYG adalah saldonya, bukan tanggal. Seluruh kode yang membaca
     * kedua kolom itu sudah tahan `null` — keadaan yang sama sudah berlaku
     * untuk paket coba gratis.
     */
    public function switchToPayg(Workspace $workspace): void
    {
        $subscription = $this->ensureFor($workspace);

        DB::transaction(function () use ($workspace, $subscription) {
            $subscription->forceFill([
                'plan_slug' => 'payg',
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => null,
                'past_due_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ])->save();

            $workspace->forceFill([
                'plan_slug' => 'payg',
                'billing_mode' => 'payg',
                'status' => 'active',
                'service_until' => null,
            ] + Plan::get('payg')->limits())->save();
        });

        AuditLog::record('subscription.payg', $subscription, [], $workspace->id);
    }

    /**
     * Menandai tagihan lunas dan memperpanjang langganan.
     *
     * `$admin` adalah orang yang menyetujui pembayaran. Selama pembayaran masih
     * dicocokkan manual, kolom itu satu-satunya jejak siapa yang memutuskan
     * uangnya benar masuk. Saat gateway otomatis nanti masuk, ia dibiarkan
     * kosong dan `note` yang menyebut referensi dari penyedia.
     */
    public function markPaid(Invoice $invoice, ?User $admin = null, ?string $note = null): Invoice
    {
        if ($invoice->isPaid()) {
            return $invoice;
        }

        $invoice = DB::transaction(function () use ($invoice, $admin, $note) {
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $admin?->id,
                'note' => $note,
            ])->save();

            $workspace = $invoice->workspace;

            /*
             | Percabangan paling berisiko di seluruh penagihan.
             |
             | Satu kesalahan di sini berarti pelanggan membayar dan tidak
             | menerima apa pun: tagihan isi saldo yang salah diperlakukan
             | sebagai langganan memperpanjang periode yang tidak pernah ada
             | dan TIDAK menambah saldo — uangnya masuk, saldonya nol, dan
             | tidak ada satu pun tempat yang menunjukkannya.
             |
             | Karena itu percabangannya dibaca dari `plan_slug` tagihan, bukan
             | dari keadaan workspace saat ini: keadaan workspace bisa berubah
             | antara tagihan terbit dan dibayar, sementara tagihan yang sudah
             | terbit adalah janji yang tidak boleh berubah artinya.
            */
            if ($invoice->isTopup()) {
                // Membayar tagihan topup itulah yang memindahkan workspace ke
                // PAYG — sama seperti paket berpindah saat tagihannya lunas,
                // bukan saat dipilih. Aman dipanggil pada workspace yang sudah
                // PAYG: ia menulis ulang nilai yang sama.
                if (! $workspace->isPayg()) {
                    $this->switchToPayg($workspace);
                    $workspace->refresh();
                }

                $this->balances->topUp($workspace, $invoice->amount, $invoice, $admin);

                $this->referrals->setujui($invoice);

                AuditLog::record('invoice.paid', $invoice, [
                    'jenis' => 'topup',
                    'total' => $invoice->total,
                    'saldo' => $workspace->fresh()->balance,
                    'oleh' => $admin?->email,
                ], $workspace->id);

                return $invoice->refresh();
            }

            $subscription = $invoice->subscription ?? $this->ensureFor($workspace);

            /*
             | Perpanjangan dihitung dari akhir periode berjalan kalau periode
             | itu belum lewat. Pelanggan yang membayar tiga hari lebih awal
             | tidak boleh kehilangan tiga hari yang sudah dibayarnya bulan lalu.
             |
             | Kalau sudah lewat, hitungan dimulai dari sekarang — bukan dari
             | akhir periode lama. Menyambung dari tanggal lampau berarti
             | pelanggan yang telat sebulan membayar penuh untuk periode yang
             | seluruhnya sudah berlalu, dan layanannya kembali mati seketika.
             */
            $mulai = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end->copy()
                : now();

            $subscription->forceFill([
                'plan_slug' => $invoice->plan_slug,
                'period' => $invoice->period,
                'status' => 'active',
                'current_period_start' => $mulai,
                'current_period_end' => $mulai->copy()->addMonths($invoice->periodMonths()),
                'past_due_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ])->save();

            $this->applyPlanLimits($workspace, $subscription->plan());

            // Tanggalnya ikut turun ke baris workspace supaya penolakan setelah
            // masa berlaku habis terjadi seketika, bukan menunggu job pukul 08:00.
            // Membayar sendiri mengakhiri status rekanan: mulai sekarang ia
            // pelanggan biasa, dan tagihan perpanjangannya harus terbit
            // otomatis lagi — kalau tidak, langganan yang baru dibayarnya
            // berakhir tanpa tagihan apa pun yang bisa dibayar.
            $workspace->forceFill([
                'status' => 'active',
                'service_until' => $subscription->current_period_end,
                'partner_since' => null,
                'partner_note' => null,
            ])->save();

            /*
             | Permintaan yang pernah tertahan kapasitas akhirnya jadi bayaran.
             |
             | Barisnya TIDAK dihapus — ia catatan permintaan, dan yang dihapus
             | setelah dilayani menghapus juga satu-satunya bukti bahwa kapasitas
             | pernah menghambat penjualan. Selisih `converted_at` dengan
             | `created_at` adalah lama tunggu sebenarnya, dan itulah angka yang
             | menjawab apakah kapasitas perlu ditambah sekarang atau bulan depan.
            */
            WaitlistEntry::where('workspace_id', $workspace->id)
                ->where('plan_slug', $invoice->plan_slug)
                ->whereNull('converted_at')
                ->update(['converted_at' => now()]);

            // Di dalam transaksi: komisi yang disetujui untuk tagihan yang
            // ternyata gagal ditandai lunas adalah utang ke reseller atas uang
            // yang tidak pernah masuk.
            $this->referrals->setujui($invoice);

            AuditLog::record('invoice.paid', $invoice, [
                'plan' => $invoice->plan_slug,
                'total' => $invoice->total,
                'oleh' => $admin?->email,
            ], $workspace->id);

            return $invoice->refresh();
        });

        // Pemberitahuan saldo, di luar transaksi dengan alasan yang sama seperti
        // notifikasi lain: pesan yang gagal tidak boleh menggulung balik
        // pembayaran yang uangnya sudah benar-benar masuk.
        if ($invoice->isTopup()) {
            $this->notifier->toWorkspace(
                $invoice->workspace,
                BillingMessages::balanceToppedUp(
                    $invoice->workspace,
                    $invoice->amount,
                    (int) $invoice->workspace->fresh()->balance,
                ),
                "topup:{$invoice->id}",
            );

            $this->notifikasi->keWorkspace(
                workspace: $invoice->workspace,
                type: 'balance.topped_up',
                title: 'Saldo bertambah Rp '.number_format($invoice->amount, 0, ',', '.'),
                body: 'Saldo sekarang Rp '
                    .number_format((int) $invoice->workspace->fresh()->balance, 0, ',', '.').'.',
                url: route('balance.index'),
                level: 'success',
                dedupe: "topup:{$invoice->id}",
            );

            return $invoice;
        }

        /*
         | Dikirim di luar transaksi, dengan sengaja.
         |
         | Di dalamnya, pesan yang gagal akan menggulung balik penandaan lunas —
         | pembayaran yang sudah masuk batal tercatat gara-gara WhatsApp sedang
         | tersendat. Di luar, kegagalannya tinggal satu baris log.
        */
        $this->notifier->toWorkspace(
            $invoice->workspace,
            BillingMessages::paymentConfirmed($invoice, $invoice->workspace->subscription),
            "invoice-paid:{$invoice->id}",
        );

        // Email menyusul di jalur yang sama persis, dan dengan alasan yang sama
        // ia juga di luar transaksi. Nomor tagihan boleh kosong dan yang
        // mengisinya bisa berganti ponsel; alamat penagihan jauh lebih jarang
        // berubah dan lebih mungkin dibaca orang yang memegang anggarannya.
        $this->email->toWorkspace(
            $invoice->workspace,
            new PembayaranDiterima($invoice, $invoice->workspace->subscription),
            "invoice-paid:{$invoice->id}",
        );

        $this->notifikasi->keWorkspace(
            workspace: $invoice->workspace,
            type: 'invoice.paid',
            title: 'Pembayaran dikonfirmasi',
            body: "Tagihan {$invoice->number} lunas. Layanan Anda aktif sampai "
                .($invoice->workspace->subscription?->current_period_end?->translatedFormat('j F Y') ?? '-').'.',
            url: route('billing.index'),
            level: 'success',
            dedupe: "invoice-paid:{$invoice->id}",
        );

        return $invoice;
    }

    /**
     * Periode habis tanpa pembayaran: pengiriman berhenti, nomor tetap tertaut.
     *
     * Workspace ditandai `suspended` supaya seluruh penegakan yang sudah ada —
     * `MessageDispatcher::guardWorkspace()` dan `AuthenticateApiKey` — ikut
     * berlaku tanpa perlu satu pun pemeriksaan tambahan di dalamnya.
     */
    public function markPastDue(Subscription $subscription): void
    {
        if ($subscription->status === 'past_due') {
            return;
        }

        DB::transaction(function () use ($subscription) {
            $subscription->forceFill([
                'status' => 'past_due',
                'past_due_at' => now(),
            ])->save();

            $subscription->workspace->forceFill(['status' => 'suspended'])->save();

            AuditLog::record('subscription.past_due', $subscription, [
                'workspace' => $subscription->workspace->name,
            ], $subscription->workspace_id);
        });

        $this->notifier->toWorkspace(
            $subscription->workspace,
            BillingMessages::serviceStopped($subscription),
            "past-due:{$subscription->id}",
            24 * 30,
        );
    }

    /**
     * Masa tenggang habis: sesi dilepas dari engine supaya soketnya berhenti
     * memakan sumber daya yang dibutuhkan pelanggan yang membayar.
     *
     * Memakai `disconnect()`, bukan `logout()`. Bedanya menentukan: logout
     * membuang kredensial di kedua sisi, sehingga pelanggan yang kembali
     * membayar harus scan QR ulang — dan itu alasan paling sering orang tidak
     * jadi kembali. Disconnect hanya menghentikan prosesnya; cadangan sesi
     * tetap tersimpan dan nomornya menyala lagi sendiri saat dipulihkan.
     */
    public function suspend(Subscription $subscription): void
    {
        if ($subscription->status === 'suspended') {
            return;
        }

        $subscription->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $subscription->workspace->forceFill(['status' => 'suspended'])->save();

        foreach ($subscription->workspace->sessions as $session) {
            if ($session->status === 'disconnected') {
                continue;
            }

            // Nomor perusahaan sendiri tidak ikut dilepas kalau workspace yang
            // ditumpanginya menunggak — melepasnya berarti pemberitahuan
            // penagihan kami sendiri ikut mati bersama pelanggan yang menunggak.
            if ($session->isSpecial()) {
                continue;
            }

            try {
                $this->sessions->disconnect($session);
            } catch (\Throwable $e) {
                // Engine sedang tidak terjangkau. Langganan tetap ditangguhkan;
                // sesi yang tertinggal hidup akan ikut mati saat engine di-deploy
                // ulang, dan pengirimannya sudah diblokir di sisi Laravel.
                Log::warning('Gagal memutus sesi saat menangguhkan langganan.', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLog::record('subscription.suspended', $subscription, [
            'workspace' => $subscription->workspace->name,
        ], $subscription->workspace_id);

        $this->notifier->toWorkspace(
            $subscription->workspace,
            BillingMessages::sessionsReleased($subscription),
            "suspended:{$subscription->id}",
            24 * 30,
        );
    }

    /**
     * Memberi kelonggaran tanpa pembayaran — untuk pelanggan yang uangnya sudah
     * ditransfer tapi buktinya belum sampai, dan untuk memperpanjang percobaan.
     */
    public function extend(Subscription $subscription, int $days, ?User $admin = null): void
    {
        $dari = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end->copy()
            : now();

        $subscription->forceFill([
            'status' => $subscription->status === 'canceled' ? 'canceled' : 'active',
            'current_period_end' => $dari->addDays($days),
            'past_due_at' => null,
            'suspended_at' => null,
        ])->save();

        $subscription->workspace->forceFill([
            'status' => 'active',
            'service_until' => $subscription->current_period_end,
        ])->save();

        AuditLog::record('subscription.extended', $subscription, [
            'hari' => $days,
            'oleh' => $admin?->email,
        ], $subscription->workspace_id);
    }

    /**
     * Memberi (atau memperpanjang) paket berbayar tanpa tagihan — rekanan.
     *
     * Sengaja meniru `markPaid()` sedekat mungkin, karena hasilnya harus tidak
     * bisa dibedakan dari langganan yang dibayar: status `active`, tanggal
     * berakhir yang nyata, batas paket tersalin, dan `service_until` ikut
     * turun. Tombol "ubah paket" di detail workspace TIDAK cukup untuk ini —
     * ia cuma mengganti slug, sehingga workspace coba gratis yang dipindah ke
     * Prime tetap `trialing` tanpa tanggal berakhir, alias Prime selamanya.
     *
     * Tidak ada tagihan yang dibuat, dan itu menjaga dua hal: harga perkenalan
     * dan kode referal pemiliknya tetap utuh (keduanya dibaca dari tagihan
     * LUNAS), dan laporan pendapatan tidak memuat uang yang tidak pernah masuk.
     */
    public function grantPartner(Workspace $workspace, string $planSlug, string $period, User $admin, ?string $note = null): Subscription
    {
        $plan = Plan::get($planSlug);

        if (! $plan->isSellable() || $plan->isPayg() || $plan->isFree() || $plan->slug === 'enterprise') {
            throw new InvalidArgumentException("Paket '{$planSlug}' tidak bisa diberikan sebagai paket rekanan.");
        }

        if (! in_array($period, ['monthly', 'yearly'], true)) {
            throw new InvalidArgumentException("Periode '{$period}' tidak dikenal.");
        }

        $subscription = $this->ensureFor($workspace);

        DB::transaction(function () use ($workspace, $subscription, $plan, $period, $admin, $note) {
            // Aturan yang sama dengan `markPaid()`: sisa masa yang belum lewat
            // tidak boleh hangus, entah itu dari perpanjangan rekanan
            // sebelumnya atau dari langganan yang dulu dibayar sendiri.
            $mulai = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end->copy()
                : now();

            $subscription->forceFill([
                'plan_slug' => $plan->slug,
                'period' => $period,
                'status' => 'active',
                'current_period_start' => $mulai,
                'current_period_end' => $mulai->copy()->addMonths($period === 'yearly' ? 12 : 1),
                'past_due_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ])->save();

            $this->applyPlanLimits($workspace, $plan);

            $workspace->forceFill([
                'status' => 'active',
                'service_until' => $subscription->current_period_end,
                // Workspace PAYG berpindah ke langganan, persis seperti saat
                // tagihan paket dibayar. Saldonya tidak disentuh — tetap milik
                // pelanggan dan terpakai lagi kalau suatu saat kembali ke PAYG.
                'billing_mode' => 'subscription',
                'partner_since' => $workspace->partner_since ?? now(),
                'partner_note' => filled($note) ? $note : $workspace->partner_note,
            ])->save();

            AuditLog::record('partner.granted', $subscription, [
                'plan' => $plan->slug,
                'period' => $period,
                'sampai' => $subscription->current_period_end->toDateString(),
                'oleh' => $admin->email,
            ], $workspace->id);
        });

        return $subscription->refresh();
    }

    /**
     * Mencabut status rekanan: workspace kembali ke paket coba gratis.
     *
     * Berlaku seketika, bukan menunggu periodenya habis — yang dicabut adalah
     * pemberian, dan pemberian yang tetap berjalan setelah dicabut bukan
     * pencabutan. Aturan "masa coba sekali per pemilik" dari `ensureFor()`
     * ikut berlaku: kalau pemiliknya sudah memakai masa coba di workspace lain,
     * yang ini jatuh ke `unpaid`, bukan mendapat lima pesan gratis lagi.
     *
     * Pesan yang terkirim selama menjadi rekanan tetap terhitung ke jatah coba
     * (`freeMessagesUsed()` menjumlahkan seluruh periode), jadi biasanya
     * pengirimannya langsung berhenti sampai pemiliknya membeli paket.
     */
    public function revokePartner(Workspace $workspace, User $admin): void
    {
        if (! $workspace->isPartner()) {
            return;
        }

        $subscription = $this->ensureFor($workspace);
        $gratis = Plan::free();
        $sudahPernahCoba = $this->pemilikSudahPernahCoba($workspace, $gratis);

        DB::transaction(function () use ($workspace, $subscription, $gratis, $sudahPernahCoba, $admin) {
            $subscription->forceFill([
                'plan_slug' => $sudahPernahCoba ? config('plans.default') : $gratis->slug,
                'period' => 'monthly',
                'status' => $sudahPernahCoba ? 'unpaid' : 'trialing',
                'current_period_start' => $sudahPernahCoba ? null : now(),
                'current_period_end' => null,
                'past_due_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ])->save();

            $this->applyPlanLimits($workspace, $sudahPernahCoba ? Plan::find(config('plans.default')) : $gratis);

            $workspace->forceFill([
                'status' => $sudahPernahCoba ? 'suspended' : 'active',
                'service_until' => null,
                'billing_mode' => 'subscription',
                'partner_since' => null,
                'partner_note' => null,
            ])->save();

            AuditLog::record('partner.revoked', $subscription, [
                'jatuh_ke' => $sudahPernahCoba ? 'unpaid' : $gratis->slug,
                'oleh' => $admin->email,
            ], $workspace->id);
        });
    }

    /** Pemilik workspace ini sudah memakai masa coba di workspace lain. */
    private function pemilikSudahPernahCoba(Workspace $workspace, Plan $gratis): bool
    {
        return Workspace::query()
            ->where('owner_id', $workspace->owner_id)
            ->whereKeyNot($workspace->getKey())
            ->whereHas('subscription', fn ($q) => $q->where('plan_slug', $gratis->slug))
            ->exists();
    }

    /**
     * Menyalin batas paket ke kolom workspace.
     *
     * Batas ditegakkan dari kolom, bukan dibaca dari paket setiap kali, karena
     * dua penegakan yang paling panas — kuota pesan dan jumlah sesi — berjalan
     * di jalur pengiriman dan tidak boleh memuat config per pesan. Kolom ini
     * juga yang memberi admin ruang menaikkan satu workspace tanpa memindahkan
     * seluruh pelanggan paket itu.
     */
    public function applyPlanLimits(Workspace $workspace, Plan $plan): void
    {
        /*
         | Enterprise mengambil batasnya dari KESEPAKATAN, bukan dari katalog.
         |
         | Angka di `config/plans.php` untuk slug ini sengaja kecil — ia cuma
         | cadangan kalau baris kesepakatannya hilang. Menyalinnya apa adanya ke
         | workspace yang membayar Enterprise berarti pelanggan membayar mahal
         | lalu dibatasi lebih ketat daripada paket termurah, dan tidak ada satu
         | pun galat yang muncul saat itu terjadi.
        */
        if ($plan->slug === 'enterprise' && $custom = EnterprisePlan::berlakuUntuk($workspace->id)) {
            $workspace->forceFill($custom->limits() + ['plan_slug' => 'enterprise'])->save();

            return;
        }

        $workspace->forceFill($plan->limits() + ['plan_slug' => $plan->slug])->save();
    }

    /**
     * Kode unik yang belum dipakai tagihan lain yang masih menunggu bayar
     * dengan nominal dasar sama.
     *
     * Yang dijaga adalah keunikan NOMINAL AKHIR di antara tagihan terbuka —
     * itulah satu-satunya yang terbaca di mutasi rekening. Dua tagihan lunas
     * boleh berbagi kode; keduanya sudah tidak perlu dicocokkan lagi.
     */
    private function allocateUniqueCode(int $baseAmount): int
    {
        if (! config('billing.unique_code.enabled')) {
            return 0;
        }

        $min = (int) config('billing.unique_code.min');
        $max = (int) config('billing.unique_code.max');

        // `discount_amount` WAJIB ikut dikurangkan di sini. Yang dijaga unik
        // adalah nominal yang benar-benar ditransfer pelanggan, dan sejak ada
        // kode referal, itu bukan lagi `amount + tax_amount`. Tanpa suku ini,
        // tagihan berdiskon dan tagihan tanpa diskon bisa berakhir dengan
        // nominal akhir yang persis sama — dan dua uang masuk yang identik
        // adalah persis keadaan yang kode unik ini ada untuk mencegahnya.
        $terpakai = Invoice::where('status', 'pending')
            ->where('due_at', '>', now())
            ->whereRaw('amount + tax_amount - discount_amount = ?', [$baseAmount])
            ->pluck('unique_code')
            ->all();

        $tersedia = array_values(array_diff(range($min, $max), $terpakai));

        // Seluruh 999 kode terpakai untuk satu nominal berarti ada 999 tagihan
        // terbuka pada paket yang sama. Jauh sebelum itu terjadi, pembayaran
        // sudah harus otomatis — tapi tagihan tetap harus bisa terbit.
        if ($tersedia === []) {
            return 0;
        }

        return $tersedia[random_int(0, count($tersedia) - 1)];
    }
}
