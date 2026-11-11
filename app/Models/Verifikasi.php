<?php

namespace App\Models;

use App\Enums\AlasanTolak;
use App\Enums\KeputusanVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Riwayat keputusan verifikasi (append-only: tidak boleh diubah atau dihapus).
 */
class Verifikasi extends Model
{
    protected $table = 'verifikasi';

    protected $fillable = ['verifikator_id', 'keputusan', 'alasan', 'catatan'];

    protected function casts(): array
    {
        return [
            'keputusan' => KeputusanVerifikasi::class,
            'alasan' => AlasanTolak::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat verifikasi tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat verifikasi tidak boleh dihapus.'));
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }
}
