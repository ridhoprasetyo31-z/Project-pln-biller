<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Hanya membaca baris; validasi dan penyimpanan di job ImporPelanggan.
 */
class PelangganImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
