<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HasilTusbug: string
{
    use HasOptions;

    case Diputus = 'diputus';
    case BayarDiTempat = 'bayar_di_tempat';
    case Ditunda = 'ditunda';
    case TidakDapatDiakses = 'tidak_dapat_diakses';

    public function label(): string
    {
        return match ($this) {
            self::Diputus => 'Diputus',
            self::BayarDiTempat => 'Bayar di tempat',
            self::Ditunda => 'Ditunda',
            self::TidakDapatDiakses => 'Tidak dapat diakses',
        };
    }
}
