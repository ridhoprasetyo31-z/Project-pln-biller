<?php

namespace Database\Seeders;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Services\KpiCalculator;
use Illuminate\Database\Seeder;

/**
 * Hitung skor dari data demo: final untuk periode yang ditutup,
 * sementara untuk periode yang berjalan.
 */
class SkorKinerjaSeeder extends Seeder
{
    public function run(KpiCalculator $calculator): void
    {
        foreach (Periode::orderBy('kode')->get() as $periode) {
            if ($periode->status === StatusPeriode::Draft) {
                continue;
            }
            $calculator->hitung($periode, $periode->status === StatusPeriode::Ditutup);
        }
    }
}
