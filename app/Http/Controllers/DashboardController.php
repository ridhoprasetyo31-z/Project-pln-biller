<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Jobs\HitungSkorKinerja;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\SkorKinerja;
use App\Models\Unit;
use App\Services\KinerjaReport;
use App\Support\Opsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, KinerjaReport $report): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'periode' => ['nullable', 'string', 'size:6'],
            'unit_id' => ['nullable', 'integer'],
        ]);

        // Koordinator selalu terkunci ke unitnya; super admin boleh memilih unit.
        $unitId = $user->role === Role::KoordinatorAdmin ? $user->unit_id : ($filters['unit_id'] ?? null);

        $periodeOptions = Periode::query()
            ->whereIn('id', SkorKinerja::select('periode_id'))
            ->orderByDesc('kode')
            ->get();

        // Bawaan: periode final terbaru (skor sementara periode berjalan masih bergerak).
        $periode = isset($filters['periode'])
            ? $periodeOptions->firstWhere('kode', $filters['periode'])
            : ($periodeOptions->firstWhere('status', StatusPeriode::Ditutup) ?? $periodeOptions->first());

        $props = [
            'filters' => ['periode' => $periode?->kode, 'unit_id' => $unitId],
            'periodeOptions' => $periodeOptions->map(fn (Periode $p) => [
                'value' => $p->kode,
                'label' => $p->nama().($p->status === StatusPeriode::Ditutup ? '' : ' (sementara)'),
            ]),
            'unitOptions' => $user->role === Role::SuperAdmin ? Opsi::unit() : null,
            'unitNama' => $unitId ? Unit::whereKey($unitId)->value('nama') : 'Semua unit',
            'periodeBerjalan' => Periode::berjalan()?->only(['id', 'kode']),
            'bisaVerifikasi' => $user->role === Role::KoordinatorAdmin,
        ];

        if (! $periode) {
            return Inertia::render('Dashboard/Index', $props + ['periode' => null]);
        }

        $peringkat = $report->peringkat($periode, $unitId);
        $sebelum = $report->periodeSebelum($periode);
        $bobot = Pengaturan::ambil('bobot_kpi');

        return Inertia::render('Dashboard/Index', $props + [
            'periode' => [
                'kode' => $periode->kode,
                'nama' => $periode->nama(),
                'final' => $periode->status === StatusPeriode::Ditutup,
                'berjalan' => $periode->status === StatusPeriode::Berjalan,
                'dihitung_at' => $peringkat->max('dihitung_at'),
                'sebelum' => $sebelum?->nama(),
            ],
            'peringkat' => $peringkat,
            'ringkasan' => $report->ringkasan($periode, $unitId, $peringkat),
            'tren' => $report->tren($periode, $unitId),
            'anomali' => $report->anomaliTerbaru($periode, $unitId),
            'ruteOptions' => Rute::query()
                ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama'])
                ->map(fn (Rute $r) => ['value' => $r->id, 'label' => "{$r->kode} · {$r->nama}"]),
            'bobot' => $bobot,
            'ambang' => [
                'pembinaan' => (float) Pengaturan::ambil('ambang_pembinaan'),
                'dipantau' => (float) Pengaturan::ambil('ambang_dipantau'),
            ],
        ]);
    }

    /** Hitung ulang skor sementara periode berjalan sekarang juga (tanpa menunggu jadwal 01.00). */
    public function hitungUlang(): RedirectResponse
    {
        $periode = Periode::berjalan();

        if (! $periode) {
            return back()->with('error', 'Tidak ada periode yang berjalan.');
        }

        HitungSkorKinerja::dispatchSync($periode->id);

        return to_route('dashboard', ['periode' => $periode->kode])
            ->with('success', "Skor sementara periode {$periode->kode} dihitung ulang.");
    }
}
