<?php

namespace App\Verifikasi;

use App\Enums\JenisPelanggan;

/**
 * Perbandingan nilai lama vs baru PDIL untuk ditampilkan.
 */
class PdilView
{
    public const LABEL = [
        'nama' => 'Nama',
        'alamat' => 'Alamat',
        'kelurahan' => 'Kelurahan',
        'jenis_pelanggan' => 'Jenis',
        'golongan_tarif' => 'Golongan tarif',
        'daya_va' => 'Daya',
        'nomor_meter' => 'Nomor meter',
        'letak_meter' => 'Letak meter',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
    ];

    /**
     * @param  array<string, mixed>|null  $lama  null untuk pelanggan baru
     * @param  array<string, mixed>  $baru
     * @return list<array{kolom: string, label: string, lama: string|null, baru: string|null, berubah: bool}>
     */
    public static function perbandingan(?array $lama, array $baru): array
    {
        return collect(self::LABEL)->map(function (string $label, string $kolom) use ($lama, $baru) {
            $a = $lama === null ? null : self::teks($kolom, $lama[$kolom] ?? null);
            $b = self::teks($kolom, $baru[$kolom] ?? null);

            return [
                'kolom' => $kolom,
                'label' => $label,
                'lama' => $a,
                'baru' => $b,
                'berubah' => $lama !== null && $a !== $b,
            ];
        })->values()->all();
    }

    private static function teks(string $kolom, mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return match ($kolom) {
            'daya_va' => number_format((int) $v, 0, ',', '.').' VA',
            'jenis_pelanggan' => JenisPelanggan::tryFrom((string) $v)?->label() ?? (string) $v,
            'latitude', 'longitude' => number_format((float) $v, 7, '.', ''),
            default => (string) $v,
        };
    }
}
