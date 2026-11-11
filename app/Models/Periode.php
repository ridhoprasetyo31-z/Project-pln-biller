<?php

namespace App\Models;

use App\Enums\StatusPeriode;
use App\Models\Concerns\AuditMaster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    use AuditMaster, HasFactory;

    protected $table = 'periode';

    protected $fillable = ['kode', 'tanggal_mulai', 'tanggal_selesai', 'status', 'ditutup_at', 'ditutup_oleh'];

    protected function casts(): array
    {
        return [
            'status' => StatusPeriode::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'ditutup_at' => 'datetime',
        ];
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function scopeBerjalan(Builder $query): void
    {
        $query->where('status', StatusPeriode::Berjalan);
    }

    public static function berjalan(): ?self
    {
        return static::query()->berjalan()->latest('kode')->first();
    }

    /** "202610" → "Oktober 2026". */
    public function nama(): string
    {
        return CarbonImmutable::createFromFormat('Ym', $this->kode)
            ->startOfMonth()
            ->locale('id')
            ->translatedFormat('F Y');
    }

    public function bisaDiubah(): bool
    {
        return $this->status !== StatusPeriode::Ditutup;
    }
}
