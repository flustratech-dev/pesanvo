<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseLead;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\PerkiraanEnterprise;
use App\Support\PhoneNumber;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Form "Hubungi kami" untuk paket Enterprise.
 *
 * Terbuka untuk pengunjung yang belum punya akun, dan itu disengaja: yang
 * paling sering butuh lebih dari Elite adalah orang yang sedang menimbang
 * apakah produk ini sanggup, bukan pelanggan yang sudah masuk. Memaksa mereka
 * mendaftar lebih dulu berarti kehilangan mereka di langkah yang tidak perlu
 * ada.
 *
 * Rate limit-nya dipasang di rute (`throttle:enterprise`), bukan di sini —
 * form publik tanpa batas adalah undangan bagi bot untuk mengisi tabel ini
 * sampai daftar yang sungguhan tidak bisa ditemukan lagi.
 */
class EnterpriseLeadController extends Controller
{
    /**
     * Halaman Enterprise dengan kalkulator perkiraannya sendiri.
     *
     * Halaman tersendiri, bukan satu blok di halaman harga: yang menimbang
     * Enterprise butuh memasukkan angkanya lalu melihat hasilnya berubah, dan
     * itu percakapan yang tidak muat di sela-sela tiga kartu paket. Alamatnya
     * sendiri juga berarti tim bisa mengirimkannya langsung ke calon pelanggan.
     */
    public function show(): View
    {
        return view('enterprise', [
            'komponen' => PerkiraanEnterprise::komponen(),
            'plan' => Plan::get('enterprise'),
            'kapasitas' => (int) config('gateway.engine.max_sessions'),
            'whatsapp' => PhoneNumber::normalize((string) config('billing.enterprise.whatsapp')),
        ]);
    }

    public function store(
        Request $request,
        WhatsAppNotifier $notifier,
        EmailNotifier $email,
        Notifier $notifikasi,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['required', 'string', 'max:30'],
            'estimated_sessions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'estimated_messages' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'want_retention_months' => ['nullable', 'integer', 'in:12,24'],
            'want_api_rate' => ['nullable', 'integer', 'in:300,600'],
            'want_onboarding' => ['nullable', 'boolean'],
            'needs' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nama',
            'company' => 'perusahaan',
            'phone' => 'nomor WhatsApp',
            'estimated_sessions' => 'perkiraan jumlah nomor',
            'estimated_messages' => 'perkiraan pesan per bulan',
            'needs' => 'kebutuhan',
        ]);

        /*
         | Perkiraannya dihitung ULANG di server, bukan diterima dari formulir.
         |
         | Angka yang datang dari peramban bisa disunting siapa saja, dan
         | penawaran yang disusun di atas angka kiriman pengunjung adalah
         | penawaran yang harganya ditentukan pemohon. Yang tersimpan tetap
         | angka yang MEREKA LIHAT — hitungannya sama persis, dari komponen
         | yang sama — supaya tim tidak menyebut angka lebih tinggi daripada
         | yang tertera di layar saat mereka memutuskan menghubungi kami.
        */
        $perkiraan = PerkiraanEnterprise::hitung(
            nomor: (int) ($data['estimated_sessions'] ?? 1),
            pesan: (int) ($data['estimated_messages'] ?? 0),
            retensi24: (int) ($data['want_retention_months'] ?? 12) === 24,
            api600: (int) ($data['want_api_rate'] ?? 300) === 600,
            pendampingan: (bool) ($data['want_onboarding'] ?? false),
        );

        $lead = EnterpriseLead::create($data + [
            'estimate_monthly' => $perkiraan['bulanan'],
            'estimate_yearly' => $perkiraan['tahunan'],
            'estimate_once' => $perkiraan['sekali'],
            // Diisi kalau kebetulan sedang login, supaya admin tidak perlu
            // mencocokkan email dengan akun secara manual.
            'user_id' => $request->user()?->id,
            'workspace_id' => $request->user() ? session('current_workspace_id') : null,
            'status' => 'baru',
        ]);

        /*
         | Tim dikabari lewat WhatsApp DAN email.
         |
         | Permintaan penawaran adalah calon pelanggan terbesar yang pernah
         | mengetuk, dan yang menentukan ia jadi atau tidak hampir selalu
         | seberapa cepat dijawab. WhatsApp saja berarti satu titik yang kalau
         | mati — nomor Pesanvo terputus, dan itu memang terjadi saat deploy
         | atau saat WhatsApp memutus perangkat tertaut — membuat seluruh kabar
         | diam tanpa satu pun gejala.
         |
         | Isi kebutuhannya sengaja TIDAK ikut di keduanya: pelanggan sering
         | menempelkan nama klien dan angka pendapatan di sana, dan keduanya
         | diteruskan serta diarsipkan di tempat yang tidak kami kendalikan.
        */
        $ringkas = $lead->name.($lead->company ? " · {$lead->company}" : '')."\n"
            .$lead->email.' · '.$lead->phone."\n"
            .($lead->estimated_sessions ? "Perkiraan nomor: {$lead->estimated_sessions}\n" : '')
            .($lead->estimated_messages
                ? 'Perkiraan pesan/bulan: '.number_format($lead->estimated_messages, 0, ',', '.')."\n"
                : '')
            // Angka yang MEREKA lihat ikut, supaya tim tidak menyebut angka
            // lebih tinggi daripada yang tertera di layar saat pemohon
            // memutuskan menghubungi kami.
            .'Perkiraan di layar: Rp '.number_format($perkiraan['bulanan'], 0, ',', '.')."/bln\n";

        $notifier->toAdmin(
            "*Permintaan Enterprise baru*\n\n".$ringkas."\nBuka panel admin → Enterprise untuk menjawabnya.",
            "enterprise-lead:{$lead->id}",
        );

        $email->kabarTim(
            'Permintaan Enterprise baru — '.($lead->company ?: $lead->name),
            $ringkas,
            "enterprise-lead:{$lead->id}",
            route('admin.enterprise.show', $lead->id),
        );

        $notifikasi->keAdmin(
            type: 'enterprise.lead',
            title: 'Permintaan Enterprise dari '.($lead->company ?: $lead->name),
            body: $lead->email.' · '.$lead->phone,
            url: route('admin.enterprise.show', $lead->id),
            level: 'warning',
            dedupe: "enterprise-lead:{$lead->id}",
        );

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Permintaan terkirim',
            'pesan' => 'Terima kasih, '.$lead->name.'. Tim kami menghubungi Anda lewat WhatsApp '
                .'atau email dalam 1×24 jam pada hari kerja.',
        ]);
    }
}
