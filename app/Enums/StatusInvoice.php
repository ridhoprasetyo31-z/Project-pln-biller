<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusInvoice: string
{
    use HasOptions;

    case BelumBayar = 'belum_bayar';
    case Lunas = 'lunas';
    case Menunggak = 'menunggak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::BelumBayar => 'Belum bayar',
            self::Lunas => 'Lunas',
            self::Menunggak => 'Menunggak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
