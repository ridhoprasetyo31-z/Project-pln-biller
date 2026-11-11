<?php

namespace App\Models;

use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Enums\StatusCatatan;
use App\Enums\StatusPeriode;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PencatatanMeter extends Model
{
    use HasFactory, HasVerifikasi;

    protected $table = 'pencatatan_meter';

    protected $fillable = [
        'periode_id', 'pelanggan_id', 'biller_id', 'penugasan_id', 'jenis', 'stand_lalu', 'stand_kini',
        'pemakaian_kwh', 'sisa_kwh', 'kondisi_meter', 'keterangan', 'latitude', 'longitude', 'jarak_m',
        'dikirim_at', 'tepat_waktu', 'status', 'jumlah_revisi', 'disetujui_at',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPelanggan::class,
            'kondisi_meter' => KondisiMeter::class,
            'status' => StatusCatatan::class,
            'stand_lalu' => 'float',
            'stand_kini' => 'float',
            'pemakaian_kwh' => 'float',
            'sisa_kwh' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'jarak_m' => 'float',
            'tepat_waktu' => 'boolean',
            'jumlah_revisi' => 'integer',
            'dikirim_at' => 'datetime',
            'disetujui_at' => 'datetime',
        ];
    }

    /** Catatan dari rute milik unit tertentu (wilayah kerja koordinator). */
    public function scopeDiUnit(Builder $query, ?int $unitId): void
    {
        $query->whereIn('penugasan_id', Penugasan::query()
            ->select('penugasan.id')
            ->join('rute', 'rute.id', '=', 'penugasan.rute_id')
            ->where('rute.unit_id', $unitId));
    }

    /** Catatan di periode yang belum ditutup. */
    public function scopePeriodeTerbuka(Builder $query): void
    {
        $query->whereIn('periode_id', Periode::query()->select('id')->where('status', '!=', StatusPeriode::Ditutup->value));
    }

    public function scopeMenungguVerifikasi(Builder $query): void
    {
        $query->whereIn('status', [StatusCatatan::MenungguVerifikasi->value, StatusCatatan::Ditandai->value]);
    }

    public function bisaDiubahBiller(): bool
    {
        return in_array($this->status, [StatusCatatan::Draft, StatusCatatan::Revisi], true);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(Penugasan::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
