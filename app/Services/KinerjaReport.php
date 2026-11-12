<?php

namespace App\Services;

use App\Enums\StatusCatatan;
use App\Models\Anomali;
use App\Models\PencatatanMeter;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\SkorKinerja;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Menyusun data kinerja (peringkat, ringkasan, tren, anomali) untuk dashboard,
 * halaman Kinerja saya, dan ekspor laporan. Cakupan: satu unit, atau semua unit bila null.
 */
class KinerjaReport
{
    /** Status label dari skor final: "pembinaan" (< ambang_pembinaan) atau "dipantau" (< ambang_dipantau). */
    public static function status(float $skor, bool $final): ?string
    {
        if (! $final) {
            return null;
        }
        if ($skor < (float) Pengaturan::ambil('ambang_pembinaan')) {
            return 'pembinaan';
        }
        if ($skor < (float) Pengaturan::ambil('ambang_dipantau')) {
            return 'dipantau';
        }

        return null;
    }

    /** Periode sebelum periode ini (untuk tren). */
    public function periodeSebelum(Periode $periode): ?Periode
    {
        return Periode::where('kode', '<', $periode->kode)->orderByDesc('kode')->first();
    }

    /**
     * Baris peringkat. Bila cakupan semua unit, peringkat disusun ulang lintas unit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function peringkat(Periode $periode, ?int $unitId): Collection
    {
        $sebelum = $this->periodeSebelum($periode);
        $skorLalu = $sebelum
            ? SkorKinerja::where('periode_id', $sebelum->id)->pluck('skor', 'biller_id')
            : collect();

        $rute = Penugasan::query()
            ->where('periode_id', $periode->id)
            ->with('rute:id,kode,nama')
            ->get()
            ->groupBy('biller_id');

        $baris = SkorKinerja::query()
            ->where('periode_id', $periode->id)
            ->with('biller:id,name,nip,unit_id,foto_profil_path', 'biller.unit:id,nama')
            ->when($unitId, fn ($q) => $q->whereHas('biller', fn ($b) => $b->where('unit_id', $unitId)))
            ->get()
            ->map(function (SkorKinerja $s) use ($skorLalu, $rute) {
                $lalu = $skorLalu->get($s->biller_id);

                return [
                    'biller_id' => $s->biller_id,
                    'nama' => $s->biller->name,
                    'nip' => $s->biller->nip,
                    'foto_url' => $s->biller->fotoProfilUrl(),
                    'unit' => $s->biller->unit?->nama,
                    'rute' => $rute->get($s->biller_id, collect())->map(fn ($p) => [
                        'id' => $p->rute_id,
                        'kode' => $p->rute->kode,
                        'nama' => $p->rute->nama,
                    ])->values()->all(),
                    'target' => $s->target,
                    'tercatat' => $s->tercatat,
                    'terverifikasi' => $s->terverifikasi,
                    'disetujui_tanpa_revisi' => $s->disetujui_tanpa_revisi,
                    'jumlah_tepat_waktu' => $s->jumlah_tepat_waktu,
                    'jumlah_bukti_lengkap' => $s->jumlah_bukti_lengkap,
                    'akurasi' => $s->akurasi,
                    'penyelesaian' => $s->penyelesaian,
                    'ketepatan_waktu' => $s->ketepatan_waktu,
                    'bukti_lengkap' => $s->bukti_lengkap,
                    'skor' => $s->skor,
                    'skor_lalu' => $lalu !== null ? (float) $lalu : null,
                    // Tren hanya untuk skor final; skor sementara masih naik seiring penyelesaian.
                    'tren' => $lalu !== null && $s->is_final ? round($s->skor - (float) $lalu, 2) : null,
                    'peringkat' => $s->peringkat,
                    'status' => self::status($s->skor, $s->is_final),
                    'is_final' => $s->is_final,
                    'dihitung_at' => $s->dihitung_at?->toIso8601String(),
                ];
            });

        $urut = KpiCalculator::urutkan($baris)->values();

        // Lintas unit: peringkat disusun ulang dari urutan gabungan.
        return $unitId ? $urut : $urut->map(fn ($b, $i) => ['peringkat' => $i + 1] + $b);
    }

    /**
     * Angka ringkas untuk 4 StatCard dashboard.
     *
     * @param  Collection<int, array<string, mixed>>  $peringkat
     */
    public function ringkasan(Periode $periode, ?int $unitId, Collection $peringkat): array
    {
        $catatan = PencatatanMeter::query()
            ->where('periode_id', $periode->id)
            ->when($unitId, fn ($q) => $q->diUnit($unitId));

        $target = (int) Penugasan::query()
            ->where('periode_id', $periode->id)
            ->when($unitId, fn ($q) => $q->whereHas('rute', fn ($r) => $r->where('unit_id', $unitId)))
            ->sum('target_pelanggan');

        $terverifikasi = $peringkat->sum('terverifikasi');

        return [
            'terkirim' => (clone $catatan)->where('status', '!=', StatusCatatan::Draft)->count(),
            'disetujui' => (clone $catatan)->where('status', StatusCatatan::Disetujui)->count(),
            'target' => $target,
            'akurasi_tim' => $terverifikasi > 0 ? round($peringkat->sum('disetujui_tanpa_revisi') / $terverifikasi * 100, 2) : null,
            'terverifikasi' => $terverifikasi,
            'menunggu' => (clone $catatan)->menungguVerifikasi()->count(),
            'ditandai' => (clone $catatan)->where('status', StatusCatatan::Ditandai)->count(),
            'anomali' => Anomali::query()
                ->where('anomaliable_type', 'pencatatan_meter')
                ->whereIn('anomaliable_id', (clone $catatan)->select('id'))
                ->count(),
        ];
    }

    /**
     * Rata-rata skor per periode, 6 periode terakhir sampai periode ini.
     *
     * @return list<array{kode: string, label: string, rata: float|null, final: bool}>
     */
    public function tren(Periode $periode, ?int $unitId, ?int $billerId = null, int $jumlah = 6): array
    {
        $daftar = Periode::where('kode', '<=', $periode->kode)->orderByDesc('kode')->limit($jumlah)->get()->reverse();

        $rata = SkorKinerja::query()
            ->whereIn('periode_id', $daftar->pluck('id'))
            ->when($billerId, fn ($q) => $q->where('biller_id', $billerId))
            ->when($unitId && ! $billerId, fn ($q) => $q->whereHas('biller', fn ($b) => $b->where('unit_id', $unitId)))
            ->groupBy('periode_id')
            ->selectRaw('periode_id, AVG(skor) as rata, MIN(is_final) as final')
            ->get()
            ->keyBy('periode_id');

        return $daftar->map(fn (Periode $p) => [
            'kode' => $p->kode,
            'label' => CarbonImmutable::createFromFormat('Ym', $p->kode)->startOfMonth()->locale('id')->translatedFormat('M y'),
            'rata' => isset($rata[$p->id]) ? round((float) $rata[$p->id]->rata, 2) : null,
            'final' => (bool) ($rata[$p->id]->final ?? false),
        ])->values()->all();
    }

    /**
     * Anomali terbaru yang masih menunggu keputusan koordinator.
     *
     * @return list<array<string, mixed>>
     */
    public function anomaliTerbaru(Periode $periode, ?int $unitId, int $jumlah = 6): array
    {
        $catatanId = PencatatanMeter::query()
            ->where('periode_id', $periode->id)
            ->where('status', StatusCatatan::Ditandai)
            ->when($unitId, fn ($q) => $q->diUnit($unitId))
            ->select('id');

        return Anomali::query()
            ->where('anomaliable_type', 'pencatatan_meter')
            ->whereIn('anomaliable_id', $catatanId)
            ->with('anomaliable.pelanggan:id,idpel,nama', 'anomaliable.biller:id,name')
            ->latest('id')
            ->limit($jumlah)
            ->get()
            ->map(fn (Anomali $a) => [
                'id' => $a->id,
                'catatan_id' => $a->anomaliable_id,
                'jenis' => $a->jenis->value,
                'label' => $a->jenis->label(),
                'keterangan' => $a->keterangan,
                'pelanggan' => $a->anomaliable->pelanggan->nama,
                'idpel' => $a->anomaliable->pelanggan->idpel,
                'biller' => $a->anomaliable->biller->name,
                'waktu' => $a->created_at->toIso8601String(),
            ])
            ->all();
    }
}
