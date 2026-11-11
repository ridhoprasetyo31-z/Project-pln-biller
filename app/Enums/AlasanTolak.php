<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AlasanTolak: string
{
    use HasOptions;

    case AngkaTidakSesuai = 'angka_tidak_sesuai';
    case FotoBuram = 'foto_buram';
    case BukanMeterPelanggan = 'bukan_meter_pelanggan';
    case DataTidakLengkap = 'data_tidak_lengkap';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::AngkaTidakSesuai => 'Angka tidak sesuai foto',
            self::FotoBuram => 'Foto buram',
            self::BukanMeterPelanggan => 'Bukan meter pelanggan ini',
            self::DataTidakLengkap => 'Data tidak lengkap',
            self::Lainnya => 'Lainnya',
        };
    }
}
