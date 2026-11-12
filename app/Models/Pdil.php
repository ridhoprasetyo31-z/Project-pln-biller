<?php

namespace App\Models;

use App\Enums\JenisPdil;
use App\Enums\JenisPelanggan;
use App\Enums\StatusCatatan;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendataan pelanggan (PDIL): usulan pelanggan baru atau perubahan data pelanggan.
 */
class Pdil extends Model
{
    use HasVerifikasi;

    /** Kolom data pelanggan yang bisa diusulkan lewat PDIL. */
    public const KOLOM_DATA = [
        'nama', 'alamat', 'kelurahan', 'jenis_pelanggan', 'golongan_tarif', 'daya_va',
        'nomor_meter', 'letak_meter', 'latitude', 'longitude',
    ];

    protected $table = 'pdil';

    protected $fillable = [
        'jenis', 'pelanggan_id', 'biller_id', 'rute_id', 'idpel', 'nama', 'alamat', 'kelurahan',
        'jenis_pelanggan', 'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude',
        'data_lama', 'keterangan', 'status', 'jumlah_revisi',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPdil::class,
            'jenis_pelanggan' => JenisPelanggan::class,
            'status' => StatusCatatan::class,
            'daya_va' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'data_lama' => 'array',
            'jumlah_revisi' => 'integer',
        ];
    }

    public function scopeDiUnit(Builder $query, ?int $unitId): void
    {
        $query->whereIn('rute_id', Rute::query()->select('id')->where('unit_id', $unitId));
    }

    public function bisaDiubahBiller(): bool
    {
        return in_array($this->status, [StatusCatatan::Draft, StatusCatatan::Revisi], true);
    }

    /** Data usulan dalam bentuk yang sama dengan data_lama. */
    public function dataBaru(): array
    {
        return collect(self::KOLOM_DATA)->mapWithKeys(fn ($k) => [
            $k => $this->{$k} instanceof \BackedEnum ? $this->{$k}->value : $this->{$k},
        ])->all();
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function rute(): BelongsTo
    {
        return $this->belongsTo(Rute::class);
    }
}
