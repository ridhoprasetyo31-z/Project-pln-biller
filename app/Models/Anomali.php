<?php

namespace App\Models;

use App\Enums\JenisAnomali;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Anomali extends Model
{
    protected $table = 'anomali';

    protected $fillable = ['jenis', 'nilai', 'keterangan'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisAnomali::class,
            'nilai' => 'float',
        ];
    }

    public function anomaliable(): MorphTo
    {
        return $this->morphTo();
    }
}
