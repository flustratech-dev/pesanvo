<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\Ticket;
use App\Services\HelpdeskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Menu Bantuan: daftar tiket, buat tiket, balas.
 *
 * **Tersedia untuk SEMUA workspace, termasuk yang langganannya mati.** Rutenya
 * sengaja di luar grup `subscription` — pelanggan yang layanannya berhenti
 * justru yang paling butuh menghubungi kami, dan menutup helpdesk untuk mereka
 * adalah kesalahan yang paling mahal: satu-satunya jalur yang tersisa jadi
 * mencari nomor kami sendiri, dan yang tidak menemukannya berhenti jadi
 * pelanggan tanpa pernah bilang kenapa.
 */
class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.tickets.index', [
            'tickets' => $workspace->tickets()
                ->withCount('messages')
                ->orderByRaw("CASE WHEN status = 'closed' THEN 1 ELSE 0 END")
                ->latest('last_reply_at')
                ->get(),
            'kategori' => Ticket::kategori(),
            'jenisLampiran' => HelpdeskService::LAMPIRAN_DITERIMA,
            'maksLampiranMb' => (int) (HelpdeskService::LAMPIRAN_MAKS_KB / 1024),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Ticket::kategori()))],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'body' => ['required', 'string', 'max:5000'],
            'lampiran' => $this->aturanLampiran(),
        ], [], ['body' => 'isi pesan', 'subject' => 'judul']);

        $ticket = $this->helpdesk->buatTiket($workspace, $request->user(), $data, $request->file('lampiran'));

        return redirect()->route('tickets.show', $ticket->id)->with('swal', [
            'tipe' => 'success',
            'judul' => 'Tiket terkirim',
            'pesan' => "Tiket #{$ticket->id} sudah kami terima. Balasannya akan dikabari lewat WhatsApp dan email.",
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $ticket = $this->milikWorkspace($request, $id);

        return view('dashboard.tickets.show', [
            'ticket' => $ticket->load('messages.user'),
            'jenisLampiran' => HelpdeskService::LAMPIRAN_DITERIMA,
            'maksLampiranMb' => (int) (HelpdeskService::LAMPIRAN_MAKS_KB / 1024),
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->milikWorkspace($request, $id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'lampiran' => $this->aturanLampiran(),
        ], [], ['body' => 'isi balasan']);

        $this->helpdesk->balasPelanggan($ticket, $request->user(), $data['body'], $request->file('lampiran'));

        return back()->with('status', 'Balasan terkirim.');
    }

    /** Pelanggan boleh menutup tiketnya sendiri kalau masalahnya sudah selesai. */
    public function close(Request $request, int $id): RedirectResponse
    {
        $this->helpdesk->tutup($this->milikWorkspace($request, $id));

        return back()->with('status', 'Tiket ditutup. Anda tetap bisa membalas untuk membukanya lagi.');
    }

    public function attachment(Request $request, int $id, int $messageId)
    {
        $ticket = $this->milikWorkspace($request, $id);

        // Dicari LEWAT tiketnya, bukan dicari sendiri lalu dicocokkan: id pesan
        // milik workspace lain tidak akan pernah ketemu di sini, jadi tidak ada
        // pemeriksaan yang bisa terlewat ditulis.
        return $this->helpdesk->unduhLampiran($ticket->messages()->findOrFail($messageId));
    }

    /**
     * Selalu berangkat dari workspace, tidak pernah `Ticket::find()`.
     *
     * Tidak ada global scope yang menangkap kelalaian ini — id tiket milik
     * workspace lain akan terbuka apa adanya kalau dicari langsung.
     */
    private function milikWorkspace(Request $request, int $id): Ticket
    {
        return EnsureWorkspaceSelected::from($request)->tickets()->findOrFail($id);
    }

    private function aturanLampiran(): array
    {
        return [
            'nullable',
            'file',
            'max:'.HelpdeskService::LAMPIRAN_MAKS_KB,
            'mimes:'.implode(',', HelpdeskService::LAMPIRAN_DITERIMA),
        ];
    }
}
