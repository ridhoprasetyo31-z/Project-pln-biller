<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum KeputusanVerifikasi: string
{
    use HasOptions;

    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }
}
