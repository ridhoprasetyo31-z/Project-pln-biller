<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusTusbug: string
{
    use HasOptions;

    case Ditugaskan = 'ditugaskan';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Ditandai = 'ditandai';
    case Revisi = 'revisi';
    case Disetujui = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Ditugaskan => 'Ditugaskan',
            self::MenungguVerifikasi => 'Menunggu verifikasi',
            self::Ditandai => 'Ditandai sistem',
            self::Revisi => 'Perlu revisi',
            self::Disetujui => 'Disetujui',
        };
    }
}
