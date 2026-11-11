<?php

namespace App\Models;

use App\Models\Concerns\AuditMaster;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    use AuditMaster, HasFactory;

    protected $table = 'tarif';

    protected $fillable = [
        'golongan', 'daya_min_va', 'daya_max_va', 'harga_per_kwh', 'biaya_beban', 'berlaku_mulai', 'berlaku_sampai',
    ];

    protected function casts(): array
    {
        return [
            'daya_min_va' => 'integer',
            'daya_max_va' => 'integer',
            'harga_per_kwh' => 'decimal:2',
            'biaya_beban' => 'decimal:2',
            'berlaku_mulai' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function scopeBerlakuPada(Builder $query, CarbonInterface $tanggal): void
    {
        $query->whereDate('berlaku_mulai', '<=', $tanggal)
            ->where(fn (Builder $q) => $q->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $tanggal));
    }

    /** Tarif yang berlaku untuk golongan + daya pada tanggal tertentu. */
    public static function untuk(string $golongan, int $dayaVa, CarbonInterface $tanggal): ?self
    {
        return static::query()
            ->where('golongan', $golongan)
            ->where('daya_min_va', '<=', $dayaVa)
            ->where('daya_max_va', '>=', $dayaVa)
            ->berlakuPada($tanggal)
            ->latest('berlaku_mulai')
            ->first();
    }
}
