<?php

namespace Database\Seeders;

use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Tarif demo golongan R1 (rumah tangga), B1 (bisnis kecil), S2 (sosial).
 * Angka mendekati tarif tenaga listrik 2025, untuk keperluan demo.
 */
class TarifSeeder extends Seeder
{
    public function run(): void
    {
        $tarif = [
            ['R1', 450, 900, 1352.00, 0],
            ['R1', 1300, 2200, 1444.70, 0],
            ['R1', 3500, 5500, 1699.53, 0],
            ['B1', 450, 5500, 1444.70, 20000],
            ['S2', 450, 900, 605.00, 9000],
            ['S2', 1300, 200000, 708.00, 9000],
        ];

        foreach ($tarif as [$golongan, $min, $max, $harga, $beban]) {
            Tarif::updateOrCreate(
                ['golongan' => $golongan, 'daya_min_va' => $min, 'daya_max_va' => $max, 'berlaku_mulai' => '2025-01-01'],
                ['harga_per_kwh' => $harga, 'biaya_beban' => $beban, 'berlaku_sampai' => null],
            );
        }
    }
}
