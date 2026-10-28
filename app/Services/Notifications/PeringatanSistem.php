<?php

namespace App\Services\Notifications;

/**
 * Satu temuan pengawas kesehatan, dikirim ke jalur yang sesuai beratnya.
 *
 * Ada sebagai kelas tersendiri karena aturan eskalasinya harus hidup di **satu**
 * tempat. Kalau tiap pemeriksaan memutuskan sendiri apakah perlu email atau
 * tidak, yang terjadi bukan ketidakrapian melainkan sesuatu yang lebih buruk:
 * pemeriksaan baru yang ditambahkan nanti akan lupa mengeskalasi, dan
 * kegagalan yang paling parah justru yang paling mungkin cuma berakhir di
 * lonceng notifikasi yang tidak dibuka siapa pun sampai besok pagi.
 *
 * ## Aturan eskalasi
 *
 * | Tingkat | Lonceng | Email | WhatsApp |
 * |---|---|---|---|
 * | `info`, `warning` | ya | tidak | tidak |
 * | `danger` | ya | ya | ya |
 *
 * Yang menahan `warning` di lonceng saja adalah pertimbangan yang lebih penting
 * daripada kelengkapan: peringatan yang berbunyi di ponsel tiap hari untuk hal
 * yang ternyata normal melatih orang mengabaikannya — dan yang terabaikan
 * berikutnya adalah yang sungguhan. Antrean panjang dan kapasitas hampir penuh
 * memang perlu dilihat, tapi tidak perlu membangunkan siapa pun.
 *
 * ## Apa yang TIDAK bisa dijangkau kelas ini
 *
 * Ketiga jalurnya berjalan **di dalam aplikasi yang sedang diawasi**. Kalau
 * containernya mati total, tidak satu pun dari ketiganya terkirim — dan jalur
 * WhatsApp bahkan lebih sempit lagi: ia memakai gateway ini sendiri, jadi
 * peringatan "engine mati" adalah peringatan yang justru tidak bisa lewat
 * WhatsApp. Itu disengaja dan bukan cacat yang bisa ditambal dari dalam:
 * sesuatu yang mati tidak bisa melaporkan kematiannya sendiri.
 *
 * Yang menutup lubang itu ada di luar — denyut ke pemantau eksternal di
 * `RekamStatusJob`, lihat `config/monitoring.php`. Email tetap dipasang di sini
 * karena ia satu-satunya jalur yang selamat dari matinya engine WhatsApp, yang
 * jauh lebih sering terjadi daripada matinya seluruh container.
 */
class PeringatanSistem
{
    public function __construct(
        private readonly Notifier $notifikasi,
        private readonly EmailNotifier $email,
        private readonly WhatsAppNotifier $wa,
    ) {}

    /**
     * @param  string  $dedupe  Kunci penanda TANPA tanggal — tanggalnya ditambahkan
     *                          di sini supaya satu keadaan menghasilkan satu kabar
     *                          per hari, berapa kali pun pengawasnya berjalan.
     */
    public function kabari(
        string $type,
        string $title,
        string $body,
        string $url,
        string $level,
        string $dedupe,
    ): void {
        $penanda = $dedupe.':'.now()->format('Y-m-d');

        $this->notifikasi->keAdmin(
            type: $type,
            title: $title,
            body: $body,
            url: $url,
            level: $level,
            dedupe: $penanda,
        );

        if ($level !== 'danger') {
            return;
        }

        /*
         | Email lebih dulu, WhatsApp sesudahnya.
         |
         | Urutannya penting justru untuk kegagalan yang paling parah: sebagian
         | besar temuan `danger` menyangkut engine atau antrean, dan keduanya
         | adalah jalur yang dipakai WhatsApp untuk mengirim. Mendahulukan
         | WhatsApp berarti percobaan yang paling mungkin gagal berjalan lebih
         | dulu — dan meskipun notifier tidak pernah melempar galat, ia tetap
         | menunggu batas waktu jaringannya sebelum menyerah.
        */
        $this->email->kabarTim($title, $body."\n\n".$url, $penanda, $url);

        $this->wa->toAdmin("*{$title}*\n\n{$body}\n\n{$url}", $penanda);
    }
}
