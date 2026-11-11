<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisLampiran: string
{
    use HasOptions;

    case Meter = 'meter';
    case LayarMeter = 'layar_meter';
    case Rumah = 'rumah';
    case Sebelum = 'sebelum';
    case Sesudah = 'sesudah';
    case Bukti = 'bukti';

    public function label(): string
    {
        return match ($this) {
            self::Meter => 'Foto meter',
            self::LayarMeter => 'Foto layar meter',
            self::Rumah => 'Foto rumah',
            self::Sebelum => 'Foto sebelum',
            self::Sesudah => 'Foto sesudah',
            self::Bukti => 'Foto bukti',
        };
    }
}
