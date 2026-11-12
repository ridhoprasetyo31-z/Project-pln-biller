<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Exports\PeringkatExport;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\SkorKinerja;
use App\Models\Unit;
use App\Services\KinerjaReport;
use App\Support\Opsi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan peringkat biller per periode: daftar periode dan unduhan Excel/PDF.
 */
class LaporanController extends Controller
{
    public function __construct(private readonly KinerjaReport $report) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $unitId = $this->unit($request);

        $periode = Periode::query()
            ->whereIn('id', SkorKinerja::select('periode_id'))
            ->orderByDesc('kode')
            ->get()
            ->map(function (Periode $p) use ($unitId) {
                $skor = SkorKinerja::where('periode_id', $p->id)
                    ->when($unitId, fn ($q) => $q->whereHas('biller', fn ($b) => $b->where('unit_id', $unitId)));

                return [
                    'kode' => $p->kode,
                    'nama' => $p->nama(),
                    'final' => $p->status === StatusPeriode::Ditutup,
                    'jumlah_biller' => (clone $skor)->count(),
                    'rata' => round((float) (clone $skor)->avg('skor'), 2),
                    'dihitung_at' => (clone $skor)->max('dihitung_at'),
                ];
            });

        return Inertia::render('Laporan/Index', [
            'periode' => $periode,
            'unitId' => $unitId,
            'unitOptions' => $user->role === Role::SuperAdmin ? Opsi::unit() : null,
            'unitNama' => $unitId ? Unit::whereKey($unitId)->value('nama') : 'Semua unit',
        ]);
    }

    public function excel(Request $request, string $kode): BinaryFileResponse
    {
        [$periode, $unitId, $baris] = $this->data($request, $kode);

        return Excel::download(
            new PeringkatExport($baris, "Peringkat {$periode->kode}"),
            $this->namaBerkas($periode, $unitId, 'xlsx'),
        );
    }

    public function pdf(Request $request, string $kode): Response
    {
        [$periode, $unitId, $baris] = $this->data($request, $kode);

        $pdf = Pdf::loadView('laporan.peringkat', [
            'periode' => $periode,
            'final' => $periode->status === StatusPeriode::Ditutup,
            'unit' => $unitId ? Unit::whereKey($unitId)->value('nama') : 'Semua unit',
            'baris' => $baris,
            'ringkasan' => $this->report->ringkasan($periode, $unitId, $baris),
            'bobot' => Pengaturan::ambil('bobot_kpi'),
            'dicetak' => now(),
            'oleh' => $request->user()->name,
        ])->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download($this->namaBerkas($periode, $unitId, 'pdf'));
    }

    /** @return array{0: Periode, 1: int|null, 2: Collection} */
    private function data(Request $request, string $kode): array
    {
        $periode = Periode::where('kode', $kode)->firstOrFail();
        $unitId = $this->unit($request);

        return [$periode, $unitId, $this->report->peringkat($periode, $unitId)];
    }

    /** Koordinator terkunci ke unitnya; super admin boleh memilih (kosong = semua unit). */
    private function unit(Request $request): ?int
    {
        $user = $request->user();

        return $user->role === Role::KoordinatorAdmin ? $user->unit_id : ($request->integer('unit_id') ?: null);
    }

    private function namaBerkas(Periode $periode, ?int $unitId, string $ext): string
    {
        $unit = $unitId ? str(Unit::whereKey($unitId)->value('nama'))->slug() : 'semua-unit';

        return "peringkat-biller-{$periode->kode}-{$unit}.{$ext}";
    }
}
