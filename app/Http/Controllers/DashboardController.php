<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Tahap 1: kerangka dashboard. Skor, peringkat, dan grafik
     * diisi pada Tahap 4 setelah KpiCalculator tersedia.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $billerQuery = User::query()->where('role', Role::Biller)->where('is_active', true);

        if ($user->role === Role::KoordinatorAdmin) {
            $billerQuery->where('unit_id', $user->unit_id);
        }

        $periode = Periode::berjalan();

        return Inertia::render('Dashboard/Index', [
            'ringkasan' => [
                'jumlah_biller' => $billerQuery->count(),
            ],
            'periode' => $periode ? ['kode' => $periode->kode, 'nama' => $periode->nama()] : null,
        ]);
    }
}
