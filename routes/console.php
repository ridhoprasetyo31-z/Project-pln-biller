<?php

use App\Jobs\HitungSkorKinerja;
use App\Jobs\TandaiInvoiceMenunggak;
use App\Models\Periode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Hitung skor kinerja manual.
 *   php artisan kinerja:hitung            → periode berjalan (sementara)
 *   php artisan kinerja:hitung 202609 --final
 */
Artisan::command('kinerja:hitung {kode? : Kode periode YYYYMM} {--final : Tandai sebagai skor final}', function (?string $kode = null) {
    $periode = $kode ? Periode::where('kode', $kode)->first() : Periode::berjalan();

    if (! $periode) {
        $this->error($kode ? "Periode {$kode} tidak ditemukan." : 'Tidak ada periode yang berjalan.');

        return 1;
    }

    HitungSkorKinerja::dispatchSync($periode->id, (bool) $this->option('final'));
    $this->info("Skor periode {$periode->kode} dihitung".($this->option('final') ? ' (final).' : ' (sementara).'));

    return 0;
})->purpose('Hitung skor kinerja biller untuk satu periode');

// Invoice lewat jatuh tempo → menunggak, tiap hari pukul 00.30 WIB.
Schedule::job(new TandaiInvoiceMenunggak)
    ->dailyAt('00:30')
    ->timezone('Asia/Jakarta')
    ->name('tandai-invoice-menunggak')
    ->withoutOverlapping();

// PRD: skor sementara periode berjalan dihitung tiap malam pukul 01.00 WIB.
Schedule::job(new HitungSkorKinerja)
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->name('hitung-skor-kinerja')
    ->withoutOverlapping();
