<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatusInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoice';

    protected $fillable = [
        'nomor', 'pelanggan_id', 'periode_id', 'pencatatan_meter_id', 'tarif_id', 'pemakaian_kwh',
        'harga_per_kwh', 'biaya_beban', 'pajak', 'total', 'jatuh_tempo', 'status', 'dibayar_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusInvoice::class,
            'pemakaian_kwh' => 'float',
            'harga_per_kwh' => 'float',
            'biaya_beban' => 'float',
            'pajak' => 'float',
            'total' => 'float',
            'jatuh_tempo' => 'date',
            'dibayar_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Rekening dilunasi → pelanggan yang diputus sementara bisa kembali aktif.
        static::updated(function (Invoice $invoice) {
            if ($invoice->wasChanged('status') && $invoice->status === StatusInvoice::Lunas) {
                Pelanggan::find($invoice->pelanggan_id)?->pulihkanBilaLunas();
            }
        });
    }

    /** Pelanggan di rute milik unit tertentu. */
    public static function pelangganDiUnit(?int $unitId): QueryBuilder
    {
        return DB::table('pelanggan')
            ->join('rute', 'rute.id', '=', 'pelanggan.rute_id')
            ->where('rute.unit_id', $unitId)
            ->select('pelanggan.id');
    }

    /**
     * Pelanggan "milik" biller: di rute yang ia pegang, atau di rute yang
     * ditugaskan kepadanya pada periode berjalan. Rute pemegang tetap berlaku
     * di antara dua periode, sehingga koreksi rekening periode lalu tetap bisa diajukan.
     */
    public static function pelangganBiller(User $biller): QueryBuilder
    {
        return DB::table('pelanggan')
            ->where(fn ($q) => $q
                ->whereIn('rute_id', DB::table('rute')->where('biller_id', $biller->id)->select('id'))
                ->orWhereIn('rute_id', DB::table('penugasan')
                    ->join('periode', 'periode.id', '=', 'penugasan.periode_id')
                    ->where('periode.status', 'berjalan')
                    ->where('penugasan.biller_id', $biller->id)
                    ->select('penugasan.rute_id')))
            ->select('pelanggan.id');
    }

    public function scopeDiUnit(Builder $query, ?int $unitId): void
    {
        $query->whereIn('pelanggan_id', self::pelangganDiUnit($unitId));
    }

    /** Cakupan invoice yang boleh dilihat pengguna. */
    public function scopeTerlihatOleh(Builder $query, User $user): void
    {
        match ($user->role) {
            Role::KoordinatorAdmin => $query->diUnit($user->unit_id),
            Role::Biller => $query->whereIn('pelanggan_id', self::pelangganBiller($user)),
            default => null,
        };
    }

    public function bisaDitagih(): bool
    {
        return in_array($this->status, [StatusInvoice::BelumBayar, StatusInvoice::Menunggak], true);
    }

    public function subtotal(): float
    {
        return $this->total - $this->pajak;
    }

    public function biayaPemakaian(): float
    {
        return $this->subtotal() - $this->biaya_beban;
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function pencatatanMeter(): BelongsTo
    {
        return $this->belongsTo(PencatatanMeter::class);
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    public function kunjungan(): HasMany
    {
        return $this->hasMany(KunjunganPenagihan::class);
    }

    public function kunjunganTerakhir(): HasOne
    {
        return $this->hasOne(KunjunganPenagihan::class)->latestOfMany('dikunjungi_at');
    }

    public function koreksi(): HasMany
    {
        return $this->hasMany(KoreksiRekening::class);
    }

    public function tusbug(): BelongsToMany
    {
        return $this->belongsToMany(Tusbug::class, 'tusbug_invoice');
    }
}
