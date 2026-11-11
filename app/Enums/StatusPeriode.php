<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusPeriode: string
{
    use HasOptions;

    case Draft = 'draft';
    case Berjalan = 'berjalan';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Berjalan => 'Berjalan',
            self::Ditutup => 'Ditutup',
        };
    }
}
