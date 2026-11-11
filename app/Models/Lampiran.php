<?php

namespace App\Models;

use App\Enums\JenisLampiran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = [
        'jenis', 'path', 'thumbnail_path', 'ukuran_kb', 'latitude', 'longitude', 'diambil_at', 'diunggah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisLampiran::class,
            'ukuran_kb' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'diambil_at' => 'datetime',
        ];
    }

    public function lampiranable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk('public')->url($this->thumbnail_path ?? $this->path);
    }
}
