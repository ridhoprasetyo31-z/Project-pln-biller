<?php

namespace App\Providers;

use App\Models;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nama pendek di kolom *_type (anomali, verifikasi, lampiran, activity_log).
        Relation::enforceMorphMap([
            'user' => Models\User::class,
            'unit' => Models\Unit::class,
            'rute' => Models\Rute::class,
            'pelanggan' => Models\Pelanggan::class,
            'periode' => Models\Periode::class,
            'penugasan' => Models\Penugasan::class,
            'tarif' => Models\Tarif::class,
            'pengaturan' => Models\Pengaturan::class,
            'pencatatan_meter' => Models\PencatatanMeter::class,
            'invoice' => Models\Invoice::class,
            'kunjungan_penagihan' => Models\KunjunganPenagihan::class,
            'koreksi_rekening' => Models\KoreksiRekening::class,
            'pdil' => Models\Pdil::class,
            'tusbug' => Models\Tusbug::class,
        ]);

        // PRD 8: eager loading. Lazy loading dilarang di luar produksi agar N+1 cepat ketahuan.
        Model::preventLazyLoading(! $this->app->isProduction());

        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');
    }
}
