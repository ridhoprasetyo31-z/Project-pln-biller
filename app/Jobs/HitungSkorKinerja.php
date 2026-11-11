<?php

namespace App\Jobs;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Services\KpiCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hitung skor kinerja. Tanpa periode → periode yang sedang berjalan (skor sementara,
 * dijadwalkan tiap malam 01.00). Saat periode ditutup dipanggil dengan $final = true.
 */
class HitungSkorKinerja implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $periodeId = null,
        public bool $final = false,
    ) {}

    public function handle(KpiCalculator $calculator): void
    {
        $periode = $this->periodeId ? Periode::find($this->periodeId) : Periode::berjalan();

        if (! $periode) {
            return;
        }

        // Skor final tidak ditimpa oleh perhitungan sementara.
        if (! $this->final && $periode->status === StatusPeriode::Ditutup) {
            return;
        }

        $calculator->hitung($periode, $this->final);
    }
}
