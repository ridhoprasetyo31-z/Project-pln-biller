<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\KpiRequest;
use App\Models\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class KpiController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('viewAny', Pengaturan::class);

        $riwayat = Activity::query()
            ->where('log_name', 'pengaturan')
            ->with('causer')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'kunci' => $a->attribute_changes?->get('attributes')['kunci'] ?? null,
                'nilai' => $a->attribute_changes?->get('attributes')['nilai'] ?? null,
                'oleh' => $a->causer?->name,
                'waktu' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('Pengaturan/Kpi/Edit', [
            'pengaturan' => Pengaturan::semua(),
            'bawaan' => collect(Pengaturan::KUNCI)->mapWithKeys(fn ($k) => [$k => config("kinerja.{$k}")]),
            'riwayat' => $riwayat,
        ]);
    }

    public function update(KpiRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $sekarang = Pengaturan::semua();

        DB::transaction(function () use ($data, $sekarang, $request) {
            foreach (Pengaturan::KUNCI as $kunci) {
                $nilai = $kunci === 'bobot_kpi'
                    ? array_map('intval', $data['bobot_kpi'])
                    : ($kunci === 'ppj_persen' ? (float) $data[$kunci] : (int) $data[$kunci]);

                if ($nilai != ($sekarang[$kunci] ?? null)) {
                    Pengaturan::simpan($kunci, $nilai, $request->user()->id);
                }
            }
        });

        return back()->with('success', 'Bobot dan ambang KPI disimpan. Skor dihitung ulang dengan nilai baru pada perhitungan berikutnya.');
    }
}
