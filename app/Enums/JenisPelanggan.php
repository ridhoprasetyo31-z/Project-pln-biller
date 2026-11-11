<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisPelanggan: string
{
    use HasOptions;

    case Pascabayar = 'pascabayar';
    case Prabayar = 'prabayar';

    public function label(): string
    {
        return match ($this) {
            self::Pascabayar => 'Pascabayar',
            self::Prabayar => 'Prabayar',
        };
    }
}
