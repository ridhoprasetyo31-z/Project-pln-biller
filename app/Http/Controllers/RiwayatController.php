<?php

namespace App\Http\Controllers;

use App\Enums\StatusCatatan;
use App\Models\PencatatanMeter;
use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Semua catatan yang pernah dikirim biller, lintas periode.
 */
class RiwayatController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'periode_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(StatusCatatan::class)],
        ]);

        $catatan = PencatatanMeter::query()
            ->with(['pelanggan:id,idpel,nama,alamat,jenis', 'periode:id,kode', 'anomali:id,anomaliable_id,anomaliable_type,jenis'])
            ->where('biller_id', $request->user()->id)
            ->where('status', '!=', StatusCatatan::Draft)
            ->when($filters['periode_id'] ?? null, fn ($q, $id) => $q->where('periode_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('pelanggan', fn ($p) => $p->where(fn ($w) => $w
                ->where('nama', 'like', "%{$term}%")
                ->orWhere('idpel', 'like', "{$term}%"))))
            ->orderByDesc('dikirim_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PencatatanMeter $c) => [
                'id' => $c->id,
                'periode' => $c->periode->kode,
                'pelanggan' => $c->pelanggan->only(['idpel', 'nama', 'alamat']),
                'jenis' => $c->jenis->value,
                'stand_lalu' => $c->stand_lalu,
                'stand_kini' => $c->stand_kini,
                'pemakaian_kwh' => $c->pemakaian_kwh,
                'sisa_kwh' => $c->sisa_kwh,
                'status' => $c->status->value,
                'anomali' => $c->anomali->first()?->jenis->value,
                'jumlah_revisi' => $c->jumlah_revisi,
                'dikirim_at' => $c->dikirim_at?->toIso8601String(),
                'tepat_waktu' => $c->tepat_waktu,
            ]);

        return Inertia::render('Riwayat/Index', [
            'catatan' => $catatan,
            'filters' => (object) $filters,
            'periodeOptions' => Periode::query()
                ->whereIn('id', PencatatanMeter::where('biller_id', $request->user()->id)->select('periode_id'))
                ->orderByDesc('kode')
                ->get(['id', 'kode'])
                ->map(fn (Periode $p) => ['value' => $p->id, 'label' => $p->nama()]),
        ]);
    }
}
