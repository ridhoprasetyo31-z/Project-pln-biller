<?php

namespace App\Services;

use App\Enums\PenyebabKoreksi;
use App\Enums\StatusCatatan;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\SkorKinerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung skor kinerja biller (catat meter) untuk satu periode — PRD bagian 4.
 *
 *   Skor = bobot_akurasi·Akurasi + bobot_penyelesaian·Penyelesaian
 *        + bobot_tepat_waktu·TepatWaktu + bobot_bukti·BuktiLengkap   (bobot dari tabel pengaturan)
 *
 * Definisi angka mentah (hanya catatan berstatus "disetujui" yang masuk skor):
 *   target                 = jumlah target_pelanggan semua penugasan biller di periode itu
 *   tercatat               = catatan disetujui
 *   terverifikasi          = catatan yang sudah diputuskan koordinator: disetujui, atau pernah ditolak
 *   disetujui_tanpa_revisi = catatan disetujui, jumlah_revisi = 0, dan tanpa koreksi rekening
 *                            "salah catat biller" yang disetujui
 *   jumlah_tepat_waktu     = catatan disetujui yang dikirim sebelum batas periode
 *   jumlah_bukti_lengkap   = catatan disetujui yang punya foto meter dan jarak GPS ≤ ambang
 *
 *   Akurasi       = disetujui_tanpa_revisi ÷ terverifikasi
 *   Penyelesaian  = tercatat ÷ target (maksimal 100%)
 *   TepatWaktu    = jumlah_tepat_waktu ÷ tercatat
 *   BuktiLengkap  = jumlah_bukti_lengkap ÷ tercatat
 *
 * Peringkat disusun per unit: skor tertinggi dulu; bila seri, akurasi lebih tinggi menang,
 * lalu penyelesaian, lalu nama.
 */
class KpiCalculator
{
    /**
     * @return Collection<int, SkorKinerja>
     */
    public function hitung(Periode $periode, bool $final = false): Collection
    {
        $pengaturan = Pengaturan::semua();
        $bobot = $pengaturan['bobot_kpi'];
        $ambangJarak = (float) $pengaturan['ambang_jarak_m'];

        $penugasan = Penugasan::query()
            ->where('periode_id', $periode->id)
            ->with('biller:id,name,unit_id')
            ->get()
            ->groupBy('biller_id');

        $mentah = $this->angkaMentah($periode->id, $ambangJarak)->keyBy('biller_id');
        $sekarang = now();

        $hasil = $penugasan->map(function (Collection $tugas, int $billerId) use ($mentah, $bobot) {
            $m = $mentah->get($billerId);
            $target = (int) $tugas->sum('target_pelanggan');
            $tercatat = (int) ($m->tercatat ?? 0);
            $terverifikasi = (int) ($m->terverifikasi ?? 0);
            $tanpaRevisi = (int) ($m->tanpa_revisi ?? 0);
            $tepat = (int) ($m->tepat_waktu ?? 0);
            $bukti = (int) ($m->bukti_lengkap ?? 0);

            $akurasi = self::persen($tanpaRevisi, $terverifikasi);
            $penyelesaian = min(100.0, self::persen($tercatat, $target));
            $ketepatan = self::persen($tepat, $tercatat);
            $buktiLengkap = self::persen($bukti, $tercatat);

            $skor = ($bobot['akurasi'] * $akurasi
                + $bobot['penyelesaian'] * $penyelesaian
                + $bobot['tepat_waktu'] * $ketepatan
                + $bobot['bukti_lengkap'] * $buktiLengkap) / 100;

            return [
                'biller_id' => $billerId,
                'unit_id' => $tugas->first()->biller?->unit_id,
                'nama' => $tugas->first()->biller?->name ?? '',
                'target' => $target,
                'tercatat' => $tercatat,
                'terverifikasi' => $terverifikasi,
                'disetujui_tanpa_revisi' => $tanpaRevisi,
                'jumlah_tepat_waktu' => $tepat,
                'jumlah_bukti_lengkap' => $bukti,
                'akurasi' => round($akurasi, 2),
                'penyelesaian' => round($penyelesaian, 2),
                'ketepatan_waktu' => round($ketepatan, 2),
                'bukti_lengkap' => round($buktiLengkap, 2),
                'skor' => round($skor, 2),
            ];
        })->values();

        // Peringkat per unit.
        $peringkat = [];
        foreach ($hasil->groupBy('unit_id') as $baris) {
            foreach (self::urutkan($baris)->values() as $i => $b) {
                $peringkat[$b['biller_id']] = $i + 1;
            }
        }

        return DB::transaction(function () use ($periode, $hasil, $peringkat, $final, $sekarang) {
            SkorKinerja::where('periode_id', $periode->id)
                ->whereNotIn('biller_id', $hasil->pluck('biller_id'))
                ->delete();

            return $hasil->map(fn (array $b) => SkorKinerja::updateOrCreate(
                ['periode_id' => $periode->id, 'biller_id' => $b['biller_id']],
                collect($b)->except(['unit_id', 'nama'])->all() + [
                    'peringkat' => $peringkat[$b['biller_id']],
                    'is_final' => $final,
                    'dihitung_at' => $sekarang,
                ],
            ));
        });
    }

    /**
     * Urutan peringkat: skor ↓, akurasi ↓, penyelesaian ↓, nama ↑.
     *
     * @template T of array|object
     *
     * @param  Collection<int, T>  $baris
     * @return Collection<int, T>
     */
    public static function urutkan(Collection $baris): Collection
    {
        $nilai = fn ($b, string $k) => is_array($b) ? $b[$k] : $b->{$k};

        return $baris->sort(function ($a, $b) use ($nilai) {
            return [$nilai($b, 'skor'), $nilai($b, 'akurasi'), $nilai($b, 'penyelesaian'), $nilai($a, 'nama')]
                <=> [$nilai($a, 'skor'), $nilai($a, 'akurasi'), $nilai($a, 'penyelesaian'), $nilai($b, 'nama')];
        });
    }

    public static function persen(int|float $pembilang, int|float $penyebut): float
    {
        return $penyebut > 0 ? $pembilang / $penyebut * 100 : 0.0;
    }

    /** Satu query agregat per biller untuk periode ini. */
    private function angkaMentah(int $periodeId, float $ambangJarak): Collection
    {
        $disetujui = StatusCatatan::Disetujui->value;

        $adaFoto = DB::table('lampiran as l')
            ->selectRaw('1')
            ->whereColumn('l.lampiranable_id', 'pm.id')
            ->where('l.lampiranable_type', 'pencatatan_meter')
            ->whereIn('l.jenis', ['meter', 'layar_meter']);

        $koreksiSalahCatat = DB::table('koreksi_rekening as kr')
            ->selectRaw('1')
            ->whereColumn('kr.pencatatan_meter_id', 'pm.id')
            ->where('kr.penyebab', PenyebabKoreksi::SalahCatatBiller->value)
            ->where('kr.status', $disetujui);

        return DB::table('pencatatan_meter as pm')
            ->where('pm.periode_id', $periodeId)
            ->groupBy('pm.biller_id')
            ->select('pm.biller_id')
            ->selectRaw('SUM(pm.status = ?) as tercatat', [$disetujui])
            ->selectRaw('SUM(pm.status = ? OR pm.jumlah_revisi > 0) as terverifikasi', [$disetujui])
            ->selectRaw(
                'SUM(pm.status = ? AND pm.jumlah_revisi = 0 AND NOT EXISTS ('.$koreksiSalahCatat->toSql().')) as tanpa_revisi',
                [$disetujui, ...$koreksiSalahCatat->getBindings()],
            )
            ->selectRaw('SUM(pm.status = ? AND pm.tepat_waktu = 1) as tepat_waktu', [$disetujui])
            ->selectRaw(
                'SUM(pm.status = ? AND pm.jarak_m IS NOT NULL AND pm.jarak_m <= ? AND EXISTS ('.$adaFoto->toSql().')) as bukti_lengkap',
                [$disetujui, $ambangJarak, ...$adaFoto->getBindings()],
            )
            ->get();
    }
}
