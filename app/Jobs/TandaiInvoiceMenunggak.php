<?php

namespace App\Jobs;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Harian: invoice belum bayar yang lewat jatuh tempo berubah menjadi "menunggak".
 */
class TandaiInvoiceMenunggak implements ShouldQueue
{
    use Queueable;

    public function handle(): int
    {
        return Invoice::query()
            ->where('status', StatusInvoice::BelumBayar)
            ->whereDate('jatuh_tempo', '<', now()->toDateString())
            ->update(['status' => StatusInvoice::Menunggak, 'updated_at' => now()]);
    }
}
