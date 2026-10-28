<?php

namespace App\Services\Notifications;

use App\Models\Ticket;

/**
 * Isi seluruh pemberitahuan helpdesk, di satu tempat.
 *
 * Alasannya sama persis dengan `BillingMessages`, dan itu sebabnya ia kelas
 * terpisah alih-alih menumpang di sana: pelanggan menerima semuanya dari nomor
 * yang sama, dan nada yang berbeda-beda terbaca sebagai ketidakrapian — atau
 * lebih buruk, sebagai penipuan.
 *
 * Dua aturan tambahan yang khusus di sini:
 *
 * - **Isi balasan tidak pernah ikut dikirim lewat WhatsApp.** Tiket bisa memuat
 *   apa saja yang ditulis pelanggan, termasuk kunci API yang mereka tempelkan
 *   sendiri saat melaporkan masalah. WhatsApp diteruskan orang jauh lebih sering
 *   dari yang dikira, dan yang perlu diberitahukan cuma bahwa ada balasan baru.
 * - **Selalu menyebut nomor tiket dan nama workspace.** Satu orang bisa mengurus
 *   beberapa workspace, dan pesan tanpa penanda memaksa mereka menebak.
 */
class HelpdeskMessages
{
    /** Ke pelanggan: tiketnya sudah dibalas. */
    public static function balasanUntukPelanggan(Ticket $ticket): string
    {
        return "*Tiket #{$ticket->id} sudah dibalas*\n\n"
            ."Pertanyaan Anda \"{$ticket->subject}\" untuk workspace *{$ticket->workspace?->name}* "
            ."sudah kami jawab.\n\n"
            .'Buka menu Bantuan di dashboard untuk membacanya dan membalas kalau masih ada yang kurang.';
    }

    /** Ke tim kami: ada tiket baru yang belum dijawab siapa pun. */
    public static function tiketBaruUntukAdmin(Ticket $ticket): string
    {
        return "*Tiket baru #{$ticket->id}*\n\n"
            ."{$ticket->subject}\n"
            ."Workspace: {$ticket->workspace?->name}\n"
            ."Kategori: {$ticket->labelKategori()}\n"
            ."Prioritas: {$ticket->labelPrioritas()}\n\n"
            .'Buka panel admin → Tiket untuk menjawabnya.';
    }

    /** Ke tim kami: pelanggan membalas tiket yang sudah dijawab. */
    public static function balasanUntukAdmin(Ticket $ticket): string
    {
        return "*Balasan baru di tiket #{$ticket->id}*\n\n"
            ."{$ticket->subject}\n"
            ."Workspace: {$ticket->workspace?->name}\n\n"
            .'Tiket ini kembali menunggu jawaban.';
    }
}
