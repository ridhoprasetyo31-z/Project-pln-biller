<?php

namespace App\Models;

use App\Enums\StatusInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoice';

    protected $fillable = [
        'nomor', 'pelanggan_id', 'periode_id', 'pencatatan_meter_id', 'tarif_id', 'pemakaian_kwh',
        'harga_per_kwh', 'biaya_beban', 'pajak', 'total', 'jatuh_tempo', 'status', 'dibayar_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusInvoice::class,
            'pemakaian_kwh' => 'float',
            'harga_per_kwh' => 'float',
            'biaya_beban' => 'float',
            'pajak' => 'float',
            'total' => 'float',
            'jatuh_tempo' => 'date',
            'dibayar_at' => 'datetime',
        ];
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function pencatatanMeter(): BelongsTo
    {
        return $this->belongsTo(PencatatanMeter::class);
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(KunjunganPenagihan::class);
    }

    public function tusbug(): BelongsToMany
    {
        return $this->belongsToMany(Tusbug::class, 'tusbug_invoice');
    }
}
