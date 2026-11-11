<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\TarifRequest;
use App\Models\Invoice;
use App\Models\Tarif;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TarifController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tarif::class);

        $filters = $request->validate([
            'golongan' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'in:berlaku,berakhir'],
        ]);
        $hariIni = now()->startOfDay();

        $tarif = Tarif::query()
            ->when($filters['golongan'] ?? null, fn ($q, $g) => $q->where('golongan', $g))
            ->when(($filters['status'] ?? null) === 'berlaku', fn ($q) => $q->berlakuPada($hariIni))
            ->when(($filters['status'] ?? null) === 'berakhir', fn ($q) => $q->whereDate('berlaku_sampai', '<', $hariIni))
            ->orderBy('golongan')
            ->orderBy('daya_min_va')
            ->orderByDesc('berlaku_mulai')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Tarif $t) => [
                'id' => $t->id,
                'golongan' => $t->golongan,
                'daya_min_va' => $t->daya_min_va,
                'daya_max_va' => $t->daya_max_va,
                'harga_per_kwh' => (float) $t->harga_per_kwh,
                'biaya_beban' => (float) $t->biaya_beban,
                'berlaku_mulai' => $t->berlaku_mulai->toDateString(),
                'berlaku_sampai' => $t->berlaku_sampai?->toDateString(),
                'berlaku' => $t->berlaku_mulai->lte($hariIni) && (! $t->berlaku_sampai || $t->berlaku_sampai->gte($hariIni)),
            ]);

        return Inertia::render('Pengaturan/Tarif/Index', [
            'tarif' => $tarif,
            'filters' => (object) $filters,
            'options' => [
                'golongan' => Tarif::query()->distinct()->orderBy('golongan')->pluck('golongan'),
            ],
        ]);
    }

    public function store(TarifRequest $request): RedirectResponse
    {
        $tarif = Tarif::create($request->validated());

        return back()->with('success', "Tarif {$tarif->golongan} ditambahkan.");
    }

    public function update(TarifRequest $request, Tarif $tarif): RedirectResponse
    {
        $tarif->update($request->validated());

        return back()->with('success', "Tarif {$tarif->golongan} diperbarui.");
    }

    public function destroy(Tarif $tarif): RedirectResponse
    {
        $this->authorize('delete', $tarif);

        if (Invoice::where('tarif_id', $tarif->id)->exists()) {
            return back()->with('error', 'Tarif ini sudah dipakai di invoice. Isi tanggal berakhir sebagai gantinya.');
        }

        $tarif->delete();

        return back()->with('success', "Tarif {$tarif->golongan} dihapus.");
    }
}
