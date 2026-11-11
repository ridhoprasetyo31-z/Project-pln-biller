<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StatusCatatan: string
{
    use HasOptions;

    case Draft = 'draft';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Ditandai = 'ditandai';
    case Revisi = 'revisi';
    case Disetujui = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf, belum dikirim',
            self::MenungguVerifikasi => 'Menunggu verifikasi',
            self::Ditandai => 'Ditandai sistem',
            self::Revisi => 'Perlu revisi',
            self::Disetujui => 'Disetujui',
        };
    }
}
