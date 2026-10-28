<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Services\HelpdeskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sisi admin helpdesk.
 *
 * Saringan bawaannya **perlu dijawab**, mengikuti pola yang sudah terbukti di
 * halaman Tagihan: yang menentukan sebuah baris perlu dilihat manusia bukan
 * seluruh daftarnya, melainkan adanya sesuatu yang belum dijawab. Daftar yang
 * bawaannya menampilkan segalanya membuat yang menunggu tenggelam di antara
 * yang sudah selesai — dan tiket yang tenggelam adalah pelanggan yang mengira
 * kami tidak menjawab.
 *
 * Terlama di atas, bukan terbaru: yang paling lama menunggu adalah yang paling
 * mendesak, dan urutan terbaru-di-atas membuat tiket lama makin tenggelam tiap
 * kali ada yang baru masuk.
 */
class TicketController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): View
    {
        $saringan = $request->query('saringan', 'perlu-dijawab');

        $daftar = Ticket::with('workspace')
            ->withCount('messages')
            ->when($saringan === 'perlu-dijawab', fn ($q) => $q->where('status', 'open'))
            ->when($saringan === 'terbuka', fn ($q) => $q->whereIn('status', ['open', 'answered']))
            ->when($saringan === 'selesai', fn ($q) => $q->where('status', 'closed'))
            ->orderBy('last_reply_at', $saringan === 'perlu-dijawab' ? 'asc' : 'desc')
            ->limit(200)
            ->get();

        return view('admin.tickets.index', [
            'tickets' => $daftar,
            'saringan' => $saringan,
            'jumlahPerluDijawab' => Ticket::where('status', 'open')->count(),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.tickets.show', [
            'ticket' => Ticket::with(['workspace', 'user', 'messages.user'])->findOrFail($id),
            'jenisLampiran' => HelpdeskService::LAMPIRAN_DITERIMA,
            'maksLampiranMb' => (int) (HelpdeskService::LAMPIRAN_MAKS_KB / 1024),
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'tutup' => ['nullable', 'boolean'],
            'lampiran' => [
                'nullable',
                'file',
                'max:'.HelpdeskService::LAMPIRAN_MAKS_KB,
                'mimes:'.implode(',', HelpdeskService::LAMPIRAN_DITERIMA),
            ],
        ], [], ['body' => 'isi balasan']);

        $this->helpdesk->balasAdmin(
            $ticket,
            $data['body'],
            $request->file('lampiran'),
            $request->boolean('tutup'),
        );

        AuditLog::record('ticket.replied', $ticket, [
            'ditutup' => $request->boolean('tutup'),
            'oleh' => $request->user()->email,
        ], $ticket->workspace_id);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => $request->boolean('tutup') ? 'Dibalas dan ditutup' : 'Balasan terkirim',
            'pesan' => 'Pelanggan dikabari lewat WhatsApp dan email.',
        ]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $ticket = Ticket::findOrFail($id);

        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);

        $data['status'] === 'closed'
            ? $this->helpdesk->tutup($ticket)
            : $this->helpdesk->bukaLagi($ticket);

        AuditLog::record('ticket.status', $ticket, [
            'status' => $data['status'],
            'oleh' => $request->user()->email,
        ], $ticket->workspace_id);

        return back()->with('status', $data['status'] === 'closed' ? 'Tiket ditutup.' : 'Tiket dibuka lagi.');
    }

    public function attachment(int $id, int $messageId)
    {
        $ticket = Ticket::findOrFail($id);

        return $this->helpdesk->unduhLampiran($ticket->messages()->findOrFail($messageId));
    }
}
