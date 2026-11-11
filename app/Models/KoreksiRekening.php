<?php

namespace App\Models;

use App\Enums\PenyebabKoreksi;
use App\Enums\StatusCatatan;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KoreksiRekening extends Model
{
    use HasVerifikasi;

    protected $table = 'koreksi_rekening';

    protected $fillable = [
        'pelanggan_id', 'periode_id', 'invoice_id', 'pencatatan_meter_id', 'diajukan_oleh', 'stand_lama',
        'stand_baru', 'kwh_lama', 'kwh_baru', 'tagihan_lama', 'tagihan_baru', 'penyebab', 'alasan', 'status',
        'jumlah_revisi',
    ];

    protected function casts(): array
    {
        return [
            'penyebab' => PenyebabKoreksi::class,
            'status' => StatusCatatan::class,
            'stand_lama' => 'float',
            'stand_baru' => 'float',
            'kwh_lama' => 'float',
            'kwh_baru' => 'float',
            'tagihan_lama' => 'float',
            'tagihan_baru' => 'float',
            'jumlah_revisi' => 'integer',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function pencatatanMeter(): BelongsTo
    {
        return $this->belongsTo(PencatatanMeter::class);
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }
}
