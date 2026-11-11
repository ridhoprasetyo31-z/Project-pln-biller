<?php

namespace App\Models\Concerns;

use App\Models\Anomali;
use App\Models\Lampiran;
use App\Models\Verifikasi;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Relasi bersama untuk semua catatan yang melewati antrean verifikasi.
 */
trait HasVerifikasi
{
    public function anomali(): MorphMany
    {
        return $this->morphMany(Anomali::class, 'anomaliable');
    }

    public function verifikasi(): MorphMany
    {
        return $this->morphMany(Verifikasi::class, 'verifiable')->latest('id');
    }

    public function verifikasiTerakhir(): MorphOne
    {
        return $this->morphOne(Verifikasi::class, 'verifiable')->latestOfMany();
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
