<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\StatusPeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\PeriodeRequest;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Services\PeriodeService;
use App\Support\Opsi;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodeController extends Controller
{
    public function __construct(private readonly PeriodeService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Periode::class);

        $periode = Periode::query()
            ->with('penutup:id,name')
            ->withCount('penugasan')
            ->withSum('penugasan', 'target_pelanggan')
            ->orderByDesc('kode')
            ->paginate(25)
            ->through(fn (Periode $p) => $this->ringkas($p) + [
                'penugasan_count' => $p->penugasan_count,
                'target_total' => (int) $p->penugasan_sum_target_pelanggan,
            ]);

        $terakhir = Periode::max('kode');
        $bulanBerikutnya = $terakhir
            ? CarbonImmutable::createFromFormat('Ym', $terakhir)->startOfMonth()->addMonth()
            : now()->toImmutable()->startOfMonth();

        return Inertia::render('Pengaturan/Periode/Index', [
            'periode' => $periode,
            'saran' => [
                'bulan' => $bulanBerikutnya->format('Y-m'),
                'tanggal_mulai' => $bulanBerikutnya->toDateString(),
                'tanggal_selesai' => $bulanBerikutnya->endOfMonth()->toDateString(),
            ],
            'adaBerjalan' => Periode::berjalan()?->kode,
        ]);
    }

    public function store(PeriodeRequest $request): RedirectResponse
    {
        $periode = $this->service->buat(
            $request->kode(),
            $request->validated('tanggal_mulai'),
            $request->validated('tanggal_selesai'),
        );
        $jumlah = $periode->penugasan()->count();

        return to_route('pengaturan.periode.show', $periode)
            ->with('success', "Periode {$periode->kode} dibuat dengan {$jumlah} penugasan dari biller pemegang rute.");
    }

    public function show(Periode $periode): Response
    {
        $this->authorize('view', $periode);
        $periode->load('penutup:id,name');

        $penugasan = Penugasan::query()
            ->where('periode_id', $periode->id)
            // select() harus sebelum withCount() agar kolom hitungan tidak tertimpa.
            ->select('penugasan.*')
            ->with(['rute:id,kode,nama,kelurahan,unit_id', 'rute.unit:id,nama', 'biller:id,name,nip,is_active'])
            ->withCount('pencatatanMeter')
            ->join('rute', 'rute.id', '=', 'penugasan.rute_id')
            ->orderBy('rute.unit_id')
            ->orderBy('rute.kode')
            ->get()
            ->map(fn (Penugasan $p) => [
                'id' => $p->id,
                'rute_id' => $p->rute_id,
                'rute' => ['kode' => $p->rute->kode, 'nama' => $p->rute->nama, 'unit_id' => $p->rute->unit_id, 'unit' => $p->rute->unit->nama],
                'biller_id' => $p->biller_id,
                'biller' => ['name' => $p->biller->name, 'nip' => $p->biller->nip, 'is_active' => $p->biller->is_active],
                'target_pelanggan' => $p->target_pelanggan,
                'pencatatan_count' => $p->pencatatan_meter_count,
            ]);

        $ruteTanpaPenugasan = Rute::query()
            ->aktif()
            ->whereNotIn('id', $penugasan->pluck('rute_id'))
            ->with('unit:id,nama')
            ->withCount(['pelanggan as pelanggan_aktif_count' => fn ($q) => $q->where('status', 'aktif')])
            ->orderBy('unit_id')
            ->orderBy('kode')
            ->get()
            ->map(fn (Rute $r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'unit_id' => $r->unit_id,
                'unit' => $r->unit->nama,
                'biller_id' => $r->biller_id,
                'pelanggan_aktif_count' => $r->pelanggan_aktif_count,
            ]);

        return Inertia::render('Pengaturan/Periode/Show', [
            'periode' => $this->ringkas($periode),
            'penugasan' => $penugasan,
            'ruteTanpaPenugasan' => $ruteTanpaPenugasan,
            'billers' => Opsi::biller(),
            'can' => [
                'ubah' => request()->user()->can('update', $periode),
                'mulai' => request()->user()->can('mulai', $periode),
                'tutup' => request()->user()->can('tutup', $periode),
                'hapus' => request()->user()->can('delete', $periode),
            ],
        ]);
    }

    public function update(PeriodeRequest $request, Periode $periode): RedirectResponse
    {
        $periode->update($request->safe()->only(['tanggal_mulai', 'tanggal_selesai']));

        return back()->with('success', "Jadwal periode {$periode->kode} diperbarui.");
    }

    public function destroy(Periode $periode): RedirectResponse
    {
        $this->authorize('delete', $periode);

        $periode->penugasan()->delete();
        $periode->delete();

        return to_route('pengaturan.periode.index')->with('success', "Periode {$periode->kode} dihapus.");
    }

    public function mulai(Periode $periode): RedirectResponse
    {
        $this->authorize('mulai', $periode);

        $this->service->mulai($periode);

        return back()->with('success', "Periode {$periode->kode} dimulai. Biller sudah bisa mencatat meter.");
    }

    public function tutup(Request $request, Periode $periode): RedirectResponse
    {
        $this->authorize('tutup', $periode);

        $this->service->tutup($periode, $request->user());

        return back()->with('success', "Periode {$periode->kode} ditutup. Catatan periode ini tidak bisa diubah lagi.");
    }

    /** @return array<string, mixed> */
    private function ringkas(Periode $p): array
    {
        return [
            'id' => $p->id,
            'kode' => $p->kode,
            'nama' => $p->nama(),
            'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'ditutup_at' => $p->ditutup_at?->toIso8601String(),
            'ditutup_oleh' => $p->relationLoaded('penutup') ? $p->penutup?->name : null,
            'bisa_diubah' => $p->status !== StatusPeriode::Ditutup,
        ];
    }
}
