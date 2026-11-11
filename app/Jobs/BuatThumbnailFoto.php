<?php

namespace App\Jobs;

use App\Models\Lampiran;
use App\Services\ImageStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Membuat thumbnail foto lampiran (dipakai di daftar antrean & riwayat).
 */
class BuatThumbnailFoto implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $lampiranId) {}

    public function handle(ImageStore $images): void
    {
        $lampiran = Lampiran::find($this->lampiranId);

        if (! $lampiran || $lampiran->thumbnail_path) {
            return;
        }

        $lampiran->update(['thumbnail_path' => $images->thumbnail($lampiran->path)]);
    }
}
