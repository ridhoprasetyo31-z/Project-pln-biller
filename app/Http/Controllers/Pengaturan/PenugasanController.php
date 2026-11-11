<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\PenugasanRequest;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Services\PenugasanService;
use Illuminate\Http\RedirectResponse;

class PenugasanController extends Controller
{
    /** Susun ulang penugasan dari biller pemegang rute. */
    public function sinkron(Periode $periode, PenugasanService $service): RedirectResponse
    {
        $this->authorize('update', $periode);

        $hasil = $service->sinkronDariRute($periode);

        $pesan = "{$hasil['dibuat']} penugasan baru, {$hasil['diperbarui']} target diperbarui.";
        if ($hasil['dilewati']) {
            $pesan .= " {$hasil['dilewati']} rute dilewati karena belum punya biller.";
        }

        return back()->with('success', $pesan);
    }

    public function store(PenugasanRequest $request, Periode $periode): RedirectResponse
    {
        $penugasan = $periode->penugasan()->create($request->validated());
        $penugasan->load('rute:id,kode', 'biller:id,name');

        return back()->with('success', "Rute {$penugasan->rute->kode} ditugaskan ke {$penugasan->biller->name}.");
    }

    public function update(PenugasanRequest $request, Penugasan $penugasan): RedirectResponse
    {
        $penugasan->update($request->validated());
        $penugasan->load('rute:id,kode', 'biller:id,name');

        return back()->with('success', "Penugasan rute {$penugasan->rute->kode} diperbarui ke {$penugasan->biller->name}.");
    }

    public function destroy(Penugasan $penugasan): RedirectResponse
    {
        $this->authorize('delete', $penugasan);

        $penugasan->load('rute:id,kode');
        $penugasan->delete();

        return back()->with('success', "Penugasan rute {$penugasan->rute->kode} dihapus.");
    }
}
