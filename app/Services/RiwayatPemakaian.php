<?php

namespace App\Services;

use App\Enums\StatusCatatan;
use App\Models\Periode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat catatan meter yang sudah disetujui dari periode-periode sebelumnya.
 * Dipakai untuk stand lalu, rata-rata 3 bulan (deteksi lonjakan), dan grafik pemakaian.
 */
class RiwayatPemakaian
{
    /**
     * Riwayat satu pelanggan sebelum periode tertentu, terbaru dulu.
     *
     * @return Collection<int, object{kode: string, stand_kini: float|null, pemakaian_kwh: float|null, sisa_kwh: float|null}>
     */
    public function untuk(int $pelangganId, Periode $sebelum, int $jumlah = 6): Collection
    {
        return DB::table('pencatatan_meter as pm')
            ->join('periode as p', 'p.id', '=', 'pm.periode_id')
            ->where('pm.pelanggan_id', $pelangganId)
            ->where('pm.status', StatusCatatan::Disetujui->value)
            ->where('p.kode', '<', $sebelum->kode)
            ->orderByDesc('p.kode')
            ->limit($jumlah)
            ->get(['p.kode', 'pm.stand_kini', 'pm.pemakaian_kwh', 'pm.sisa_kwh'])
            ->map(fn ($r) => (object) [
                'kode' => $r->kode,
                'stand_kini' => $r->stand_kini !== null ? (float) $r->stand_kini : null,
                'pemakaian_kwh' => $r->pemakaian_kwh !== null ? (float) $r->pemakaian_kwh : null,
                'sisa_kwh' => $r->sisa_kwh !== null ? (float) $r->sisa_kwh : null,
            ]);
    }

    /** Stand terakhir yang disetujui sebelum periode ini. */
    public function standLalu(int $pelangganId, Periode $periode): ?float
    {
        return $this->untuk($pelangganId, $periode, 1)->first()?->stand_kini;
    }

    /** Rata-rata pemakaian 3 periode terakhir yang disetujui. */
    public function rataTigaBulan(int $pelangganId, Periode $periode): ?float
    {
        $pemakaian = $this->untuk($pelangganId, $periode, 3)->pluck('pemakaian_kwh')->filter(fn ($v) => $v !== null);

        return $pemakaian->isEmpty() ? null : round($pemakaian->avg(), 1);
    }

    /**
     * Stand lalu untuk banyak pelanggan sekaligus (satu query, MySQL 8 window function).
     *
     * @param  list<int>  $pelangganIds
     * @return array<int, float> pelanggan_id => stand_kini terakhir
     */
    public function standLaluBanyak(array $pelangganIds, Periode $periode): array
    {
        if ($pelangganIds === []) {
            return [];
        }

        $terbaru = DB::table('pencatatan_meter as pm')
            ->join('periode as p', 'p.id', '=', 'pm.periode_id')
            ->whereIn('pm.pelanggan_id', $pelangganIds)
            ->where('pm.status', StatusCatatan::Disetujui->value)
            ->where('p.kode', '<', $periode->kode)
            ->whereNotNull('pm.stand_kini')
            ->selectRaw('pm.pelanggan_id, pm.stand_kini, ROW_NUMBER() OVER (PARTITION BY pm.pelanggan_id ORDER BY p.kode DESC) as urut');

        return DB::query()
            ->fromSub($terbaru, 't')
            ->where('urut', 1)
            ->pluck('stand_kini', 'pelanggan_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
