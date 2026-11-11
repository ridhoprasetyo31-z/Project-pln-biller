<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum JenisAnomali: string
{
    use HasOptions;

    case StandTurun = 'stand_turun';
    case Lonjakan = 'lonjakan';
    case PemakaianNol = 'pemakaian_nol';
    case LokasiJauh = 'lokasi_jauh';
    case BuktiTidakLengkap = 'bukti_tidak_lengkap';
    case IndikasiPelanggaran = 'indikasi_pelanggaran';
    case LewatJadwal = 'lewat_jadwal';

    public function label(): string
    {
        return match ($this) {
            self::StandTurun => 'Stand turun',
            self::Lonjakan => 'Lonjakan pemakaian',
            self::PemakaianNol => 'Pemakaian nol',
            self::LokasiJauh => 'Lokasi jauh dari pelanggan',
            self::BuktiTidakLengkap => 'Bukti tidak lengkap',
            self::IndikasiPelanggaran => 'Indikasi pelanggaran',
            self::LewatJadwal => 'Lewat jadwal',
        };
    }
}
