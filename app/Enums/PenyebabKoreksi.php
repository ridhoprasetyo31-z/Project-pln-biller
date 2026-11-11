<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PenyebabKoreksi: string
{
    use HasOptions;

    case SalahCatatBiller = 'salah_catat_biller';
    case SalahInputSistem = 'salah_input_sistem';
    case KomplainPelanggan = 'komplain_pelanggan';
    case MeterRusak = 'meter_rusak';

    public function label(): string
    {
        return match ($this) {
            self::SalahCatatBiller => 'Salah catat biller',
            self::SalahInputSistem => 'Salah input sistem',
            self::KomplainPelanggan => 'Komplain pelanggan',
            self::MeterRusak => 'Meter rusak',
        };
    }
}
