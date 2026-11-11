<?php

namespace App\Models;

use App\Enums\JenisUnit;
use App\Models\Concerns\AuditMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use AuditMaster, HasFactory;

    protected $table = 'units';

    protected $fillable = ['kode', 'nama', 'jenis', 'parent_id', 'alamat'];

    protected function casts(): array
    {
        return ['jenis' => JenisUnit::class];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function rute(): HasMany
    {
        return $this->hasMany(Rute::class);
    }
}
