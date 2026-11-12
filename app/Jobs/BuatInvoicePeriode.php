<?php

namespace App\Jobs;

use App\Models\Periode;
use App\Services\InvoiceGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Susulan: buat invoice untuk catatan pascabayar disetujui yang belum punya invoice.
 * (Invoice biasanya langsung dibuat saat catatan disetujui koordinator.)
 */
class BuatInvoicePeriode implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $periodeId) {}

    /** @return array{dibuat: int, dilewati: int} */
    public function handle(InvoiceGenerator $generator): array
    {
        $periode = Periode::find($this->periodeId);

        return $periode ? $generator->buatPeriode($periode) : ['dibuat' => 0, 'dilewati' => 0];
    }
}
