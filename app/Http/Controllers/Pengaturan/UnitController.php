<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\JenisUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $units = Unit::query()
            ->with('parent:id,nama')
            ->withCount(['users', 'rute', 'children'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$term}%")
                ->orWhere('kode', 'like', "{$term}%")))
            ->orderBy('jenis')
            ->orderBy('kode')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Unit $u) => [
                'id' => $u->id,
                'kode' => $u->kode,
                'nama' => $u->nama,
                'jenis' => $u->jenis->value,
                'jenis_label' => $u->jenis->label(),
                'parent_id' => $u->parent_id,
                'parent' => $u->parent?->nama,
                'alamat' => $u->alamat,
                'users_count' => $u->users_count,
                'rute_count' => $u->rute_count,
                'children_count' => $u->children_count,
            ]);

        return Inertia::render('Pengaturan/Units/Index', [
            'units' => $units,
            'filters' => (object) $filters,
            'options' => [
                'jenis' => JenisUnit::options(),
                'up3' => Unit::where('jenis', JenisUnit::Up3)->orderBy('kode')->get(['id', 'kode', 'nama']),
            ],
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());

        return back()->with('success', "{$unit->nama} ditambahkan.");
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated() + ['parent_id' => null]);

        return back()->with('success', "{$unit->nama} diperbarui.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $unit->loadCount(['users', 'rute', 'children']);
        if ($unit->users_count || $unit->rute_count || $unit->children_count) {
            return back()->with('error', "{$unit->nama} masih dipakai oleh pengguna, rute, atau ULP. Pindahkan data tersebut lebih dulu.");
        }

        $unit->delete();

        return back()->with('success', "{$unit->nama} dihapus.");
    }
}
