<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusPelanggan: string
{
    use HasOptions;

    case Aktif = 'aktif';
    case PutusSementara = 'putus_sementara';
    case Bongkar = 'bongkar';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::PutusSementara => 'Putus sementara',
            self::Bongkar => 'Bongkar',
        };
    }
}
