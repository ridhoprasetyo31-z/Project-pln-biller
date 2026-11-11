<?php

namespace App\Http\Controllers;

use App\Enums\JenisPelanggan;
use App\Enums\StatusCatatan;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\SkorKinerja;
use App\Services\RiwayatPemakaian;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TugasController extends Controller
{
    public function index(Request $request, RiwayatPemakaian $riwayat): Response
    {
        $user = $request->user();
        $periode = Periode::berjalan();

        if (! $periode) {
            return Inertia::render('Tugas/Index', ['periode' => null]);
        }

        $daftarPenugasan = Penugasan::query()
            ->with('rute:id,kode,nama,kelurahan')
            ->where('periode_id', $periode->id)
            ->where('biller_id', $user->id)
            ->get();

        $penugasan = $daftarPenugasan->firstWhere('id', $request->integer('penugasan')) ?? $daftarPenugasan->first();

        $periodeData = [
            'kode' => $periode->kode,
            'nama' => $periode->nama(),
            'tanggal_selesai' => $periode->tanggal_selesai->toDateString(),
        ];

        if (! $penugasan) {
            return Inertia::render('Tugas/Index', ['periode' => $periodeData, 'penugasan' => null]);
        }

        $pelanggan = Pelanggan::query()
            ->aktif()
            ->where('rute_id', $penugasan->rute_id)
            ->orderBy('urutan_rute')
            ->get(['id', 'idpel', 'urutan_rute', 'nama', 'alamat', 'kelurahan', 'jenis', 'golongan_tarif', 'daya_va', 'latitude', 'longitude']);

        $catatan = PencatatanMeter::query()
            ->with(['anomali:id,anomaliable_id,anomaliable_type,jenis', 'verifikasiTerakhir'])
            ->where('periode_id', $periode->id)
            ->whereIn('pelanggan_id', $pelanggan->pluck('id'))
            ->get()
            ->keyBy('pelanggan_id');

        $standLalu = $riwayat->standLaluBanyak($pelanggan->pluck('id')->all(), $periode);

        $rows = $pelanggan->map(function (Pelanggan $p) use ($catatan, $standLalu) {
            /** @var PencatatanMeter|null $c */
            $c = $catatan->get($p->id);

            return [
                'id' => $p->id,
                'urutan' => $p->urutan_rute,
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => $p->alamat,
                'jalan' => preg_replace('/\s+No\.?\s*\d+.*$/i', '', $p->alamat),
                'jenis' => $p->jenis->value,
                'tarif' => $p->labelTarif(),
                'stand_lalu' => $p->jenis === JenisPelanggan::Pascabayar ? ($standLalu[$p->id] ?? null) : null,
                'status' => $c?->status->value,
                'anomali' => $c?->anomali->first()?->jenis->value,
                'alasan_revisi' => $c?->status === StatusCatatan::Revisi ? $c->verifikasiTerakhir?->alasan?->label() : null,
                'url' => route("catat.{$p->jenis->value}", $p),
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ];
        })->values();

        $terkirim = $catatan->filter(fn (PencatatanMeter $c) => $c->status !== StatusCatatan::Draft)->count();
        $hariIni = CarbonImmutable::today();
        $sisaHari = max(1, (int) $hariIni->diffInDays($periode->tanggal_selesai, false) + 1);
        $sisaTarget = max(0, $penugasan->target_pelanggan - $terkirim);

        $periodeLalu = Periode::query()->where('kode', '<', $periode->kode)->orderByDesc('kode')->first();
        $skorLalu = $periodeLalu
            ? SkorKinerja::where('periode_id', $periodeLalu->id)->where('biller_id', $user->id)->first()
            : null;

        return Inertia::render('Tugas/Index', [
            'periode' => $periodeData,
            'penugasan' => [
                'id' => $penugasan->id,
                'rute' => $penugasan->rute->only(['kode', 'nama', 'kelurahan']),
                'target' => $penugasan->target_pelanggan,
            ],
            'daftarPenugasan' => $daftarPenugasan->map(fn (Penugasan $p) => [
                'id' => $p->id,
                'label' => "{$p->rute->kode} · {$p->rute->nama}",
            ]),
            'progres' => [
                'terkirim' => $terkirim,
                'target' => $penugasan->target_pelanggan,
                'sisa_hari' => $sisaHari,
                'lewat_batas' => $hariIni->greaterThan($periode->tanggal_selesai),
                'per_hari' => $sisaTarget > 0 ? (int) ceil($sisaTarget / $sisaHari) : 0,
            ],
            'skorLalu' => $skorLalu ? [
                'periode' => $periodeLalu->nama(),
                'skor' => $skorLalu->skor,
                'peringkat' => $skorLalu->peringkat,
                'akurasi' => $skorLalu->akurasi,
                'penyelesaian' => $skorLalu->penyelesaian,
                'ketepatan_waktu' => $skorLalu->ketepatan_waktu,
                'bukti_lengkap' => $skorLalu->bukti_lengkap,
            ] : null,
            'pelanggan' => $rows,
        ]);
    }
}
