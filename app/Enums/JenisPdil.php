<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisPdil: string
{
    use HasOptions;

    case PelangganBaru = 'pelanggan_baru';
    case PerubahanData = 'perubahan_data';

    public function label(): string
    {
        return match ($this) {
            self::PelangganBaru => 'Pelanggan baru',
            self::PerubahanData => 'Perubahan data',
        };
    }
}
