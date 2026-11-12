<?php

namespace App\Models;

use App\Enums\HasilKunjungan;
use App\Enums\StatusCatatan;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KunjunganPenagihan extends Model
{
    use HasVerifikasi;

    protected $table = 'kunjungan_penagihan';

    protected $fillable = [
        'invoice_id', 'biller_id', 'dikunjungi_at', 'hasil', 'tanggal_janji', 'nominal_dibayar', 'keterangan',
        'latitude', 'longitude', 'jarak_m', 'status', 'jumlah_revisi',
    ];

    public function scopeDiUnit(Builder $query, ?int $unitId): void
    {
        $query->whereIn('invoice_id', Invoice::query()->select('id')->diUnit($unitId));
    }

    public function bisaDiubahBiller(): bool
    {
        return in_array($this->status, [StatusCatatan::Draft, StatusCatatan::Revisi], true);
    }

    protected function casts(): array
    {
        return [
            'hasil' => HasilKunjungan::class,
            'status' => StatusCatatan::class,
            'dikunjungi_at' => 'datetime',
            'tanggal_janji' => 'date',
            'nominal_dibayar' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'jarak_m' => 'float',
            'jumlah_revisi' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }
}
