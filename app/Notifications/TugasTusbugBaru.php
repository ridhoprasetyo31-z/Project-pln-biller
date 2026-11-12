<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;

/**
 * Untuk biller: koordinator menugaskan TUSBUG (pemutusan) baru.
 */
class TugasTusbugBaru extends Notification
{
    public function __construct(
        public int $jumlah,
        public CarbonInterface $tanggalRencana,
        public string $penugas,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tanggal = $this->tanggalRencana->locale('id')->translatedFormat('j F Y');

        return [
            'jenis' => 'tusbug',
            'judul' => $this->jumlah > 1 ? "{$this->jumlah} tugas TUSBUG baru" : 'Tugas TUSBUG baru',
            'pesan' => "{$this->penugas} menugaskan pemutusan untuk dilaksanakan {$tanggal}.",
            'url' => route('tusbug.index'),
        ];
    }
}
