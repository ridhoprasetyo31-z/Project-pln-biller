<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Untuk biller: koordinator mengembalikan catatannya untuk direvisi.
 */
class CatatanDikembalikan extends Notification
{
    public function __construct(
        public string $jenisLabel,
        public string $pelanggan,
        public string $alasan,
        public ?string $catatan,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'jenis' => 'revisi',
            'judul' => "{$this->jenisLabel} dikembalikan",
            'pesan' => "{$this->pelanggan}: {$this->alasan}".($this->catatan ? ". {$this->catatan}" : '.'),
            'url' => $this->url,
        ];
    }
}
