<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Laporan peringkat biller satu periode ke Excel.
 */
class PeringkatExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithTitle
{
    /** @param  Collection<int, array<string, mixed>>  $baris */
    public function __construct(private Collection $baris, private string $judul) {}

    public function title(): string
    {
        return $this->judul;
    }

    public function headings(): array
    {
        return [
            'Peringkat', 'Nama biller', 'NIP', 'Unit', 'Rute', 'Target', 'Tercatat (disetujui)', 'Terverifikasi',
            'Disetujui tanpa revisi', 'Tepat waktu', 'Bukti lengkap', 'Akurasi (%)', 'Penyelesaian (%)',
            'Tepat waktu (%)', 'Bukti lengkap (%)', 'Skor', 'Status',
        ];
    }

    public function array(): array
    {
        return $this->baris->map(fn (array $b) => [
            $b['peringkat'],
            $b['nama'],
            $b['nip'],
            $b['unit'],
            collect($b['rute'])->pluck('kode')->join(', '),
            $b['target'],
            $b['tercatat'],
            $b['terverifikasi'],
            $b['disetujui_tanpa_revisi'],
            $b['jumlah_tepat_waktu'],
            $b['jumlah_bukti_lengkap'],
            $b['akurasi'],
            $b['penyelesaian'],
            $b['ketepatan_waktu'],
            $b['bukti_lengkap'],
            $b['skor'],
            match ($b['status']) {
                'pembinaan' => 'Perlu pembinaan',
                'dipantau' => 'Dipantau',
                default => $b['is_final'] ? 'Baik' : 'Sementara',
            },
        ])->all();
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'L' => '0.00',
            'M' => '0.00',
            'N' => '0.00',
            'O' => '0.00',
            'P' => '0.00',
        ];
    }
}
