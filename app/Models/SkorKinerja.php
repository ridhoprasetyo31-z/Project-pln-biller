<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkorKinerja extends Model
{
    protected $table = 'skor_kinerja';

    protected $fillable = [
        'periode_id', 'biller_id', 'target', 'tercatat', 'terverifikasi', 'disetujui_tanpa_revisi',
        'jumlah_tepat_waktu', 'jumlah_bukti_lengkap', 'akurasi', 'penyelesaian', 'ketepatan_waktu',
        'bukti_lengkap', 'skor', 'peringkat', 'is_final', 'dihitung_at',
    ];

    protected function casts(): array
    {
        return [
            'akurasi' => 'float',
            'penyelesaian' => 'float',
            'ketepatan_waktu' => 'float',
            'bukti_lengkap' => 'float',
            'skor' => 'float',
            'is_final' => 'boolean',
            'dihitung_at' => 'datetime',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }
}
