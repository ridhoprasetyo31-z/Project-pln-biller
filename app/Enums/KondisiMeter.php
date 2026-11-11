<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum KondisiMeter: string
{
    use HasOptions;

    case Normal = 'normal';
    case MeterRusak = 'meter_rusak';
    case RumahKosong = 'rumah_kosong';
    case AksesTertutup = 'akses_tertutup';
    case Rusak = 'rusak';
    case IndikasiPelanggaran = 'indikasi_pelanggaran';
    case LayarMati = 'layar_mati';

    /** @return list<self> */
    public static function untukPascabayar(): array
    {
        return [self::Normal, self::MeterRusak, self::RumahKosong, self::AksesTertutup];
    }

    /** @return list<self> */
    public static function untukPrabayar(): array
    {
        return [self::Normal, self::Rusak, self::IndikasiPelanggaran, self::LayarMati];
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::MeterRusak => 'Meter rusak',
            self::RumahKosong => 'Rumah kosong',
            self::AksesTertutup => 'Akses tertutup',
            self::Rusak => 'Rusak',
            self::IndikasiPelanggaran => 'Indikasi pelanggaran',
            self::LayarMati => 'Layar mati',
        };
    }
}
