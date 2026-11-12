<?php

namespace App\Services;

use App\Enums\JenisLampiran;
use App\Jobs\BuatThumbnailFoto;
use App\Models\Lampiran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan foto bukti (meter, layar meter, rumah, sebelum/sesudah, bukti) ke tabel lampiran.
 * Foto lama berjenis sama diganti. Thumbnail dibuat setelah respons terkirim.
 */
class LampiranService
{
    public function __construct(private readonly ImageStore $images) {}

    /**
     * @param  Model  $pemilik  Model yang memakai HasVerifikasi (punya relasi lampiran()).
     */
    public function ganti(
        Model $pemilik,
        UploadedFile $foto,
        JenisLampiran $jenis,
        User $pengunggah,
        string $folder,
        ?float $lat = null,
        ?float $lng = null,
        ?string $diambilAt = null,
    ): Lampiran {
        foreach ($pemilik->lampiran()->where('jenis', $jenis)->get() as $lama) {
            // Gambar contoh dari seeder dipakai bersama, jangan dihapus berkasnya.
            if (! Str::startsWith($lama->path, 'demo/')) {
                $this->images->delete($lama->path);
                $this->images->delete($lama->thumbnail_path);
            }
            $lama->delete();
        }

        $path = $this->images->storeScaled($foto, trim($folder, '/'));

        $lampiran = $pemilik->lampiran()->create([
            'jenis' => $jenis,
            'path' => $path,
            'ukuran_kb' => (int) ceil(Storage::disk('public')->size($path) / 1024),
            'latitude' => $lat,
            'longitude' => $lng,
            'diambil_at' => $diambilAt ? CarbonImmutable::parse($diambilAt) : now(),
            'diunggah_oleh' => $pengunggah->id,
        ]);

        BuatThumbnailFoto::dispatchAfterResponse($lampiran->id);

        return $lampiran;
    }
}
