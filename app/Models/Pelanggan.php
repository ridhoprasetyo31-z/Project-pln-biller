<?php

namespace App\Models;

use App\Enums\JenisPelanggan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Models\Concerns\AuditMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pelanggan extends Model
{
    use AuditMaster, HasFactory, SoftDeletes;

    protected $table = 'pelanggan';

    protected $fillable = [
        'idpel', 'rute_id', 'urutan_rute', 'nama', 'alamat', 'kelurahan', 'jenis',
        'golongan_tarif', 'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude', 'status',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPelanggan::class,
            'status' => StatusPelanggan::class,
            'urutan_rute' => 'integer',
            'daya_va' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function rute(): BelongsTo
    {
        return $this->belongsTo(Rute::class);
    }

    public function pencatatanMeter(): HasMany
    {
        return $this->hasMany(PencatatanMeter::class);
    }

    public function invoice(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeAktif(Builder $query): void
    {
        $query->where('status', StatusPelanggan::Aktif);
    }

    /**
     * Pelanggan yang diputus sementara kembali aktif bila tidak ada lagi
     * rekening menunggak (dipanggil saat rekening dilunasi).
     */
    public function pulihkanBilaLunas(): void
    {
        if ($this->status !== StatusPelanggan::PutusSementara) {
            return;
        }

        $masihMenunggak = Invoice::where('pelanggan_id', $this->id)
            ->where('status', StatusInvoice::Menunggak)
            ->exists();

        if (! $masihMenunggak) {
            $this->update(['status' => StatusPelanggan::Aktif]);
        }
    }

    /** Label tarif singkat, contoh "R1 / 1.300 VA". */
    public function labelTarif(): string
    {
        return $this->golongan_tarif.' / '.number_format($this->daya_va, 0, ',', '.').' VA';
    }
}
