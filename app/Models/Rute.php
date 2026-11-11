<?php

namespace App\Models;

use App\Models\Concerns\AuditMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rute extends Model
{
    use AuditMaster, HasFactory;

    protected $table = 'rute';

    protected $fillable = ['unit_id', 'kode', 'nama', 'kelurahan', 'biller_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class)->orderBy('urutan_rute');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    public function scopeAktif(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
