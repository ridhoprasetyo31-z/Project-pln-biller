<?php

namespace App\Models;

use App\Models\Concerns\AuditMaster;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penugasan extends Model
{
    use AuditMaster, HasFactory;

    protected $table = 'penugasan';

    protected $fillable = ['periode_id', 'rute_id', 'biller_id', 'target_pelanggan'];

    protected function casts(): array
    {
        return ['target_pelanggan' => 'integer'];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function rute(): BelongsTo
    {
        return $this->belongsTo(Rute::class);
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function pencatatanMeter(): HasMany
    {
        return $this->hasMany(PencatatanMeter::class);
    }
}
