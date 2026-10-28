<?php

namespace App\Services;

use App\Mail\TiketDibalas;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\HelpdeskMessages;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Tiket dan seluruh pemberitahuannya, di satu tempat.
 *
 * Dikumpulkan begini karena aturan yang paling mudah dilanggar di fitur ini
 * bukan soal data melainkan soal siapa yang dikabari, dan kapan. Tiga
 * peristiwa, tiga tujuan berbeda: tiket baru → tim kami, admin membalas →
 * pelanggan, pelanggan membalas → tim kami. Ditulis di controller, ketiganya
 * akan menyimpang begitu ada jalur keempat.
 *
 * Pengirimannya WAJIB lewat `WhatsAppNotifier` — tidak pernah melempar galat,
 * selalu dari sesi Pesanvo, satu peristiwa satu pesan. Menulis pengiriman
 * sendiri di sini akan melanggar ketiganya sekaligus, dan yang paling mahal
 * yang pertama: tiket yang gagal tersimpan karena WhatsApp sedang tersendat
 * adalah pelanggan yang tidak bisa mengeluh.
 */
class HelpdeskService
{
    public function __construct(
        private readonly WhatsAppNotifier $notifier,
        private readonly EmailNotifier $email,
        private readonly Notifier $notifikasi,
    ) {}

    /** Jenis lampiran yang diterima, dipakai validasi dan teks bantuan sekaligus. */
    public const LAMPIRAN_DITERIMA = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'log'];

    public const LAMPIRAN_MAKS_KB = 5120;

    /**
     * Membuat tiket baru beserta pesan pertamanya.
     *
     * Keduanya dalam satu transaksi: tiket tanpa pesan pertama adalah baris yang
     * tampil di daftar admin tanpa isi apa pun, dan tidak ada cara tahu apa yang
     * sebenarnya ditanyakan.
     */
    public function buatTiket(Workspace $workspace, User $oleh, array $data, ?UploadedFile $lampiran = null): Ticket
    {
        $ticket = DB::transaction(function () use ($workspace, $oleh, $data, $lampiran) {
            $ticket = Ticket::create([
                'workspace_id' => $workspace->id,
                'user_id' => $oleh->id,
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            $this->simpanPesan($ticket, $data['body'], $oleh, false, $lampiran);

            return $ticket;
        });

        // Di luar transaksi: pemberitahuan yang gagal tidak boleh menggulung
        // balik tiketnya sendiri.
        $this->notifier->toAdmin(
            HelpdeskMessages::tiketBaruUntukAdmin($ticket),
            "ticket-new:{$ticket->id}",
        );

        $this->email->kabarTim(
            "Tiket baru #{$ticket->id} — {$ticket->subject}",
            HelpdeskMessages::tiketBaruUntukAdmin($ticket),
            "ticket-new:{$ticket->id}",
            route('admin.tickets.show', $ticket->id),
        );

        $this->notifikasi->keAdmin(
            type: 'ticket.new',
            title: "Tiket baru #{$ticket->id}",
            body: $ticket->subject.' — '.($ticket->workspace?->name ?? 'workspace terhapus'),
            url: route('admin.tickets.show', $ticket->id),
            level: $ticket->priority === 'high' ? 'warning' : 'info',
            dedupe: "ticket-new:{$ticket->id}",
            workspace: $ticket->workspace,
        );

        return $ticket;
    }

    /**
     * Balasan pelanggan. Mengembalikan tiket ke `open`.
     *
     * Tanpa pengembalian status itu, tiket yang sudah dijawab lalu dibalas lagi
     * hilang dari saringan "perlu dijawab" dan tidak ada yang tahu masih ada
     * yang menunggu.
     */
    public function balasPelanggan(Ticket $ticket, User $oleh, string $isi, ?UploadedFile $lampiran = null): TicketMessage
    {
        $pesan = DB::transaction(function () use ($ticket, $oleh, $isi, $lampiran) {
            $pesan = $this->simpanPesan($ticket, $isi, $oleh, false, $lampiran);

            $ticket->forceFill([
                'status' => 'open',
                'last_reply_at' => now(),
                'closed_at' => null,
            ])->save();

            return $pesan;
        });

        // Penandanya memuat id pesan, bukan id tiket: pelanggan boleh membalas
        // berkali-kali, dan penanda per tiket membuat balasan kedua diam.
        $this->notifier->toAdmin(
            HelpdeskMessages::balasanUntukAdmin($ticket),
            "ticket-reply:{$pesan->id}",
        );

        $this->email->kabarTim(
            "Balasan baru di tiket #{$ticket->id}",
            HelpdeskMessages::balasanUntukAdmin($ticket),
            "ticket-reply:{$pesan->id}",
            route('admin.tickets.show', $ticket->id),
        );

        $this->notifikasi->keAdmin(
            type: 'ticket.replied_by_customer',
            title: "Balasan baru di tiket #{$ticket->id}",
            body: $ticket->subject.' — kembali menunggu jawaban.',
            url: route('admin.tickets.show', $ticket->id),
            dedupe: "ticket-reply:{$pesan->id}",
            workspace: $ticket->workspace,
        );

        return $pesan;
    }

    /** Balasan admin. Mengabari pelanggan lewat WhatsApp dan email sekaligus. */
    public function balasAdmin(Ticket $ticket, string $isi, ?UploadedFile $lampiran = null, bool $tutup = false): TicketMessage
    {
        $pesan = DB::transaction(function () use ($ticket, $isi, $lampiran, $tutup) {
            $pesan = $this->simpanPesan($ticket, $isi, null, true, $lampiran);

            $ticket->forceFill([
                'status' => $tutup ? 'closed' : 'answered',
                'last_reply_at' => now(),
                'closed_at' => $tutup ? now() : null,
            ])->save();

            return $pesan;
        });

        $ticket->refresh();

        $this->notifier->toWorkspace(
            $ticket->workspace,
            HelpdeskMessages::balasanUntukPelanggan($ticket),
            "ticket-answered:{$pesan->id}",
        );

        // Email menyusul di jalur yang sama. Nomor tagihan boleh kosong, dan
        // pelanggan yang tidak mengisinya tetap harus tahu tiketnya dijawab.
        $this->email->toWorkspace(
            $ticket->workspace,
            new TiketDibalas($ticket),
            "ticket-answered:{$pesan->id}",
        );

        $this->notifikasi->keWorkspace(
            workspace: $ticket->workspace,
            type: 'ticket.answered',
            title: "Tiket #{$ticket->id} sudah dibalas",
            body: $ticket->subject,
            url: route('tickets.show', $ticket->id),
            level: 'success',
            dedupe: "ticket-answered:{$pesan->id}",
        );

        return $pesan;
    }

    public function tutup(Ticket $ticket): void
    {
        $ticket->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
    }

    public function bukaLagi(Ticket $ticket): void
    {
        $ticket->forceFill(['status' => 'open', 'closed_at' => null, 'last_reply_at' => now()])->save();
    }

    /**
     * Menyimpan satu pesan, dengan lampirannya kalau ada.
     *
     * Lampiran masuk ke disk `media` yang privat — sama seperti bukti bayar, dan
     * dengan alasan yang sama: berkas yang disajikan lewat URL yang bisa ditebak
     * bisa dibuka siapa pun yang menebaknya, dan yang diunggah di sini justru
     * tangkapan layar berisi galat, nomor pelanggan, dan kadang kunci API.
     *
     * Nama aslinya disimpan terpisah dari path. Path-nya diacak supaya tidak
     * bisa ditebak; nama aslinya tetap dibutuhkan supaya berkas yang diunduh
     * kembali punya nama yang berarti bagi yang mengunggahnya.
     */
    private function simpanPesan(
        Ticket $ticket,
        string $isi,
        ?User $oleh,
        bool $dariAdmin,
        ?UploadedFile $lampiran,
    ): TicketMessage {
        $path = $lampiran?->store("tiket/{$ticket->workspace_id}", 'media');

        return TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $oleh?->id,
            'body' => $isi,
            'is_from_admin' => $dariAdmin,
            'attachment_path' => $path ?: null,
            'attachment_name' => $lampiran?->getClientOriginalName(),
        ]);
    }

    /** Mengirimkan lampiran ke pengunduh, setelah kepemilikannya dipastikan. */
    public function unduhLampiran(TicketMessage $pesan)
    {
        abort_unless($pesan->punyaLampiran(), 404);
        abort_unless(Storage::disk('media')->exists($pesan->attachment_path), 404);

        return response()->download(
            Storage::disk('media')->path($pesan->attachment_path),
            $pesan->attachment_name ?: 'lampiran',
        );
    }
}
