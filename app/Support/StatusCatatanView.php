<?php

namespace App\Support;

use App\Enums\JenisAnomali;
use App\Models\Anomali;
use App\Models\Lampiran;
use App\Models\PencatatanMeter;
use App\Models\Verifikasi;
use Illuminate\Support\Collection;

/**
 * Bentuk data catatan meter yang dikirim ke frontend, dipakai di beberapa halaman.
 */
class StatusCatatanView
{
    /** @return list<array{jenis: string, label: string, keterangan: string, nilai: float|null}> */
    public static function anomali(Collection $anomali): array
    {
        return $anomali
            ->map(fn (Anomali $a) => [
                'jenis' => $a->jenis->value,
                'label' => $a->jenis->label(),
                'keterangan' => $a->keterangan,
                'nilai' => $a->nilai,
            ])
            ->values()
            ->all();
    }

    public static function foto(?Lampiran $foto): ?array
    {
        return $foto ? [
            'url' => $foto->url(),
            'thumb' => $foto->thumbnailUrl(),
            'diambil_at' => $foto->diambil_at?->toIso8601String(),
            'latitude' => $foto->latitude,
            'longitude' => $foto->longitude,
            'ukuran_kb' => $foto->ukuran_kb,
        ] : null;
    }

    public static function verifikasi(?Verifikasi $v): ?array
    {
        return $v ? [
            'keputusan' => $v->keputusan->value,
            'alasan' => $v->alasan?->value,
            'alasan_label' => $v->alasan?->label(),
            'catatan' => $v->catatan,
            'oleh' => $v->relationLoaded('verifikator') ? $v->verifikator?->name : null,
            'waktu' => $v->created_at->toIso8601String(),
        ] : null;
    }

    /** Foto utama catatan (meter atau layar meter) dari relasi lampiran yang sudah dimuat. */
    public static function fotoUtama(PencatatanMeter $c): ?Lampiran
    {
        return $c->lampiran->first(fn (Lampiran $l) => in_array($l->jenis->value, ['meter', 'layar_meter'], true));
    }

    public static function anomaliUtama(PencatatanMeter $c): ?string
    {
        /** @var Anomali|null $a */
        $a = $c->anomali->first();

        return $a?->jenis instanceof JenisAnomali ? $a->jenis->value : null;
    }
}
