<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/**
 * Membuat beberapa gambar contoh (bukan foto asli) untuk lampiran data demo.
 * Gambar dipakai bersama oleh banyak catatan, disimpan di storage/app/public/demo.
 */
class FotoDemo
{
    public const METER = ['demo/meter-1.jpg', 'demo/meter-2.jpg', 'demo/meter-3.jpg', 'demo/meter-4.jpg'];

    public const LAYAR = ['demo/layar-1.jpg', 'demo/layar-2.jpg'];

    public static function pastikanAda(): void
    {
        $disk = Storage::disk('public');

        foreach (self::METER as $i => $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, self::gambar('kWh METER', [52, 64, 74][$i % 3] + $i * 6, $i));
            }
        }
        foreach (self::LAYAR as $i => $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, self::gambar('TOKEN kWh', 40 + $i * 10, $i, true));
            }
        }
    }

    private static function gambar(string $judul, int $gelap, int $varian, bool $layar = false): string
    {
        $w = 960;
        $h = 720;
        $img = imagecreatetruecolor($w, $h);

        // Dinding rumah sebagai latar.
        $dinding = imagecolorallocate($img, 150 + $varian * 8, 140 + $varian * 6, 120 + $varian * 5);
        imagefilledrectangle($img, 0, 0, $w, $h, $dinding);
        for ($y = 0; $y < $h; $y += 48) {
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, 135, 125, 108));
        }

        // Badan meter.
        $badan = imagecolorallocate($img, 224, 228, 232);
        $bayang = imagecolorallocate($img, 70, 70, 70);
        imagefilledrectangle($img, 262, 92, 712, 652, $bayang);
        imagefilledrectangle($img, 250, 80, 700, 640, $badan);

        // Panel tampilan.
        $panel = imagecolorallocate($img, $gelap, $gelap + 6, $gelap + 12);
        imagefilledrectangle($img, 300, 200, 650, 330, $panel);

        $kotak = imagecolorallocate($img, 15, 27, 42);
        $merah = imagecolorallocate($img, 200, 40, 31);
        $putih = imagecolorallocate($img, 245, 245, 245);
        $hitam = imagecolorallocate($img, 30, 30, 30);

        // Kotak-kotak digit tanpa angka (gambar contoh).
        $jumlah = $layar ? 5 : 6;
        for ($d = 0; $d < $jumlah; $d++) {
            $x = 318 + $d * 54;
            imagefilledrectangle($img, $x, 225, $x + 44, 305, $d === $jumlah - 1 && ! $layar ? $merah : $kotak);
            imageline($img, $x + 10, 265, $x + 34, 265, $putih);
        }

        imagestring($img, 5, 300, 120, $judul, $hitam);
        imagestring($img, 4, 300, 360, 'GAMBAR CONTOH - DATA DEMO', $hitam);
        imagestring($img, 3, 300, 390, 'Bukan foto meter asli', $hitam);

        // Tutup terminal & segel.
        imagefilledrectangle($img, 300, 470, 650, 600, imagecolorallocate($img, 190, 195, 200));
        imagefilledellipse($img, 475, 535, 40, 40, $merah);

        ob_start();
        imagejpeg($img, null, 78);
        $data = (string) ob_get_clean();
        imagedestroy($img);

        return $data;
    }
}
