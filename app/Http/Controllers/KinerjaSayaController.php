<?php

namespace App\Http\Controllers;

use App\Enums\StatusCatatan;
use App\Enums\StatusPeriode;
use App\Models\PencatatanMeter;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\SkorKinerja;
use App\Services\KinerjaReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Skor kinerja biller sendiri: komponen, peringkat di unit, tren, dan catatan yang mengurangi skor.
 */
class KinerjaSayaController extends Controller
{
    public function __invoke(Request $request, KinerjaReport $report): Response
    {
        $user = $request->user();

        $periodeOptions = Periode::query()
            ->whereIn('id', SkorKinerja::where('biller_id', $user->id)->select('periode_id'))
            ->orderByDesc('kode')
            ->get();

        $periode = $request->filled('periode')
            ? $periodeOptions->firstWhere('kode', $request->string('periode')->toString())
            : ($periodeOptions->firstWhere('status', StatusPeriode::Ditutup) ?? $periodeOptions->first());

        $props = [
            'periodeOptions' => $periodeOptions->map(fn (Periode $p) => [
                'value' => $p->kode,
                'label' => $p->nama().($p->status === StatusPeriode::Ditutup ? '' : ' (sementara)'),
            ]),
        ];

        if (! $periode) {
            return Inertia::render('Kinerja/Saya', $props + ['periode' => null]);
        }

        $peringkatUnit = $report->peringkat($periode, $user->unit_id);
        $saya = $peringkatUnit->firstWhere('biller_id', $user->id);

        $catatan = fn () => PencatatanMeter::query()
            ->with('pelanggan:id,idpel,nama')
            ->where('periode_id', $periode->id)
            ->where('biller_id', $user->id);
        $ambangJarak = (float) Pengaturan::ambil('ambang_jarak_m');
        $ringkas = fn (PencatatanMeter $c) => [
            'id' => $c->id,
            'nama' => $c->pelanggan->nama,
            'idpel' => $c->pelanggan->idpel,
            'jumlah_revisi' => $c->jumlah_revisi,
            'jarak_m' => $c->jarak_m,
            'dikirim_at' => $c->dikirim_at?->toIso8601String(),
        ];

        return Inertia::render('Kinerja/Saya', $props + [
            'periode' => [
                'kode' => $periode->kode,
                'nama' => $periode->nama(),
                'final' => $periode->status === StatusPeriode::Ditutup,
                'sebelum' => $report->periodeSebelum($periode)?->nama(),
            ],
            'skor' => $saya,
            'jumlahBiller' => $peringkatUnit->count(),
            'rataUnit' => round((float) $peringkatUnit->avg('skor'), 2),
            'tren' => $report->tren($periode, null, $user->id),
            'trenUnit' => $report->tren($periode, $user->unit_id),
            'bobot' => Pengaturan::ambil('bobot_kpi'),
            'ambang' => [
                'pembinaan' => (float) Pengaturan::ambil('ambang_pembinaan'),
                'dipantau' => (float) Pengaturan::ambil('ambang_dipantau'),
                'jarak_m' => $ambangJarak,
            ],
            'pengurang' => [
                'revisi' => $catatan()->where('jumlah_revisi', '>', 0)->latest('dikirim_at')->limit(10)->get()->map($ringkas),
                'terlambat' => $catatan()->where('tepat_waktu', false)->where('status', StatusCatatan::Disetujui)->latest('dikirim_at')->limit(10)->get()->map($ringkas),
                'jauh' => $catatan()->where('status', StatusCatatan::Disetujui)
                    ->where(fn ($q) => $q->whereNull('jarak_m')->orWhere('jarak_m', '>', $ambangJarak))
                    ->latest('dikirim_at')->limit(10)->get()->map($ringkas),
            ],
        ]);
    }
}
