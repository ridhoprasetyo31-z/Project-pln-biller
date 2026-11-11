<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Menyimpan foto unggahan dengan nama UUID, orientasi EXIF diperbaiki,
 * lalu dikecilkan agar hemat penyimpanan.
 */
class ImageStore
{
    public function __construct(private readonly string $disk = 'public') {}

    /** Simpan foto berbentuk persegi (avatar), kembalikan path relatif. */
    public function storeSquare(UploadedFile $file, string $dir, int $size = 512): string
    {
        $image = $this->manager()->decodePath($file->getRealPath())->orient()->cover($size, $size);

        return $this->put($dir, (string) $image->encode(new JpegEncoder(quality: 85)));
    }

    /** Simpan foto dengan sisi terpanjang maksimal $max piksel. */
    public function storeScaled(UploadedFile $file, string $dir, int $max = 1600): string
    {
        $image = $this->manager()->decodePath($file->getRealPath())->orient()->scaleDown($max, $max);

        return $this->put($dir, (string) $image->encode(new JpegEncoder(quality: 82)));
    }

    /** Buat thumbnail dari berkas yang sudah tersimpan, kembalikan path thumbnail. */
    public function thumbnail(string $path, int $max = 360): string
    {
        $disk = Storage::disk($this->disk);
        $image = $this->manager()->decodeBinary($disk->get($path))->scaleDown($max, $max);

        $thumb = dirname($path).'/thumb/'.pathinfo($path, PATHINFO_FILENAME).'.jpg';
        $disk->put($thumb, (string) $image->encode(new JpegEncoder(quality: 75)));

        return $thumb;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk)->delete($path);
        }
    }

    private function put(string $dir, string $binary): string
    {
        $path = trim($dir, '/').'/'.Str::uuid().'.jpg';
        Storage::disk($this->disk)->put($path, $binary);

        return $path;
    }

    private function manager(): ImageManager
    {
        return ImageManager::usingDriver(GdDriver::class);
    }
}
