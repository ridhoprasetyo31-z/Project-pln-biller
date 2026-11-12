<?php

namespace App\Models;

use App\Enums\HasilTusbug;
use App\Enums\StatusTusbug;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tugas pemutusan (TUSBUG) untuk pelanggan dengan tunggakan.
 */
class Tusbug extends Model
{
    use HasVerifikasi;

    protected $table = 'tusbug';

    protected $fillable = [
        'nomor_surat', 'pelanggan_id', 'biller_id', 'ditugaskan_oleh', 'jumlah_lembar', 'total_tunggakan',
        'tanggal_rencana', 'dilaksanakan_at', 'hasil', 'keterangan', 'latitude', 'longitude', 'jarak_m', 'status', 'jumlah_revisi',
    ];

    protected function casts(): array
    {
        return [
            'hasil' => HasilTusbug::class,
            'status' => StatusTusbug::class,
            'jumlah_lembar' => 'integer',
            'total_tunggakan' => 'float',
            'tanggal_rencana' => 'date',
            'dilaksanakan_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'jarak_m' => 'float',
            'jumlah_revisi' => 'integer',
        ];
    }

    public function scopeDiUnit(Builder $query, ?int $unitId): void
    {
        $query->whereIn('pelanggan_id', Invoice::pelangganDiUnit($unitId));
    }

    /** Masih terbuka: belum disetujui (ditugaskan, menunggu, ditandai, revisi). */
    public function scopeTerbuka(Builder $query): void
    {
        $query->where('status', '!=', StatusTusbug::Disetujui->value);
    }

    /** Biller boleh mengisi hasil saat masih ditugaskan atau dikembalikan untuk revisi. */
    public function bisaDilaksanakan(): bool
    {
        return in_array($this->status, [StatusTusbug::Ditugaskan, StatusTusbug::Revisi], true);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function penugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_oleh');
    }

    public function invoice(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'tusbug_invoice');
    }
}
