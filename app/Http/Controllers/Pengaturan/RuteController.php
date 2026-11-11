<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\StatusPelanggan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\RuteRequest;
use App\Models\Rute;
use App\Support\Opsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rute::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'unit_id' => ['nullable', 'integer'],
            'biller' => ['nullable', 'in:ada,kosong'],
        ]);

        $rute = Rute::query()
            ->with(['unit:id,kode,nama', 'biller:id,name,nip'])
            ->withCount([
                'pelanggan',
                'pelanggan as pelanggan_aktif_count' => fn ($q) => $q->where('status', StatusPelanggan::Aktif),
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('kode', 'like', "%{$term}%")
                ->orWhere('nama', 'like', "%{$term}%")
                ->orWhere('kelurahan', 'like', "%{$term}%")))
            ->when($filters['unit_id'] ?? null, fn ($q, $unit) => $q->where('unit_id', $unit))
            ->when(($filters['biller'] ?? null) === 'kosong', fn ($q) => $q->whereNull('biller_id'))
            ->when(($filters['biller'] ?? null) === 'ada', fn ($q) => $q->whereNotNull('biller_id'))
            ->orderBy('unit_id')
            ->orderBy('kode')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Rute $r) => [
                'id' => $r->id,
                'unit_id' => $r->unit_id,
                'unit' => $r->unit->nama,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'kelurahan' => $r->kelurahan,
                'biller_id' => $r->biller_id,
                'biller' => $r->biller ? ['name' => $r->biller->name, 'nip' => $r->biller->nip] : null,
                'is_active' => $r->is_active,
                'pelanggan_count' => $r->pelanggan_count,
                'pelanggan_aktif_count' => $r->pelanggan_aktif_count,
            ]);

        return Inertia::render('Pengaturan/Rute/Index', [
            'rute' => $rute,
            'filters' => (object) $filters,
            'options' => [
                'units' => Opsi::unit(),
                'billers' => Opsi::biller(),
            ],
        ]);
    }

    public function store(RuteRequest $request): RedirectResponse
    {
        $rute = Rute::create($request->validated());

        return back()->with('success', "Rute {$rute->kode} ditambahkan.");
    }

    public function update(RuteRequest $request, Rute $rute): RedirectResponse
    {
        $rute->update($request->validated());

        return back()->with('success', "Rute {$rute->kode} diperbarui.");
    }

    /** Ganti biller pemegang langsung dari tabel. */
    public function updateBiller(Request $request, Rute $rute): RedirectResponse
    {
        $this->authorize('update', $rute);

        $data = $request->validate(
            ['biller_id' => ['nullable', 'integer', RuteRequest::billerDiUnit($rute->unit_id)]],
            ['biller_id.exists' => 'Biller harus biller aktif dari unit yang sama dengan rute.'],
        );

        $rute->update(['biller_id' => $data['biller_id'] ?? null]);
        $rute->load('biller:id,name');

        return back()->with('success', $rute->biller
            ? "Rute {$rute->kode} sekarang dipegang {$rute->biller->name}."
            : "Biller rute {$rute->kode} dikosongkan.");
    }

    public function destroy(Rute $rute): RedirectResponse
    {
        $this->authorize('delete', $rute);

        if ($rute->pelanggan()->withTrashed()->exists() || $rute->penugasan()->exists()) {
            return back()->with('error', "Rute {$rute->kode} masih punya pelanggan atau penugasan. Nonaktifkan rute ini sebagai gantinya.");
        }

        $rute->delete();

        return back()->with('success', "Rute {$rute->kode} dihapus.");
    }
}
