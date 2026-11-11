<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HasilKunjungan: string
{
    use HasOptions;

    case Lunas = 'lunas';
    case JanjiBayar = 'janji_bayar';
    case TidakBertemu = 'tidak_bertemu';
    case Menolak = 'menolak';

    public function label(): string
    {
        return match ($this) {
            self::Lunas => 'Lunas',
            self::JanjiBayar => 'Janji bayar',
            self::TidakBertemu => 'Tidak bertemu',
            self::Menolak => 'Menolak',
        };
    }
}
