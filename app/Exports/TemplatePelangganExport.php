<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Template impor pelanggan berisi judul kolom + dua baris contoh.
 */
class TemplatePelangganExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings
{
    public const KOLOM = [
        'idpel', 'nama', 'alamat', 'kelurahan', 'kode_rute', 'urutan_rute', 'jenis', 'golongan_tarif',
        'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude', 'status',
    ];

    public function headings(): array
    {
        return self::KOLOM;
    }

    public function array(): array
    {
        return [
            ['181101000121', 'Ahmad Siregar', 'Jl. Garuda Sakti No. 61', 'Tuah Karya', 'PNM-01', 121, 'pascabayar', 'R1', 1300, '45110005477', 'Teras depan', 0.4683, 101.3795, 'aktif'],
            ['181101000122', 'Sri Lestari', 'Jl. Garuda Sakti No. 63', 'Tuah Karya', 'PNM-01', 122, 'prabayar', 'R1', 900, '45110005514', 'Pagar depan', '', '', 'aktif'],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
