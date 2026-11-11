<?php

namespace App\Models;

use App\Enums\JenisPdil;
use App\Enums\JenisPelanggan;
use App\Enums\StatusCatatan;
use App\Models\Concerns\HasVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pdil extends Model
{
    use HasVerifikasi;

    protected $table = 'pdil';

    protected $fillable = [
        'jenis', 'pelanggan_id', 'biller_id', 'rute_id', 'idpel', 'nama', 'alamat', 'kelurahan',
        'jenis_pelanggan', 'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude',
        'data_lama', 'status', 'jumlah_revisi',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPdil::class,
            'jenis_pelanggan' => JenisPelanggan::class,
            'status' => StatusCatatan::class,
            'daya_va' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'data_lama' => 'array',
            'jumlah_revisi' => 'integer',
        ];
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'biller_id');
    }

    public function rute(): BelongsTo
    {
        return $this->belongsTo(Rute::class);
    }
}
