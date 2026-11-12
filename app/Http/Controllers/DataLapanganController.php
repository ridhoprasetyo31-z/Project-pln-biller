<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use App\Models\SkorKinerja;
use App\Models\User;
use App\Services\KinerjaReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data lapangan untuk koordinator (hanya baca, sebatas unitnya):
 * pencatatan, pelanggan, rute, dan biller. Perubahan data master tetap lewat super admin.
 */
class DataLapanganController extends Controller
{
    public function pencatatan(Request $request): Response
    {
        $unitId = $request->user()->unit_id;
        $filters = $request->validate([
            'periode' => ['nullable', 'string', 'size:6'],
            'status' => ['nullable', Rule::enum(StatusCatatan::class)],
            'rute_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $periode = isset($filters['periode']) ? Periode::where('kode', $filters['periode'])->first() : (Periode::berjalan() ?? Periode::orderByDesc('kode')->first());

        $catatan = PencatatanMeter::query()
            ->diUnit($unitId)
            ->when($periode, fn ($q) => $q->where('periode_id', $periode->id))
            ->with('pelanggan:id,idpel,nama,alamat,rute_id', 'pelanggan.rute:id,kode', 'biller:id,name', 'anomali:id,anomaliable_id,anomaliable_type,jenis')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['rute_id'] ?? null, fn ($q, $r) => $q->whereIn('pelanggan_id', DB::table('pelanggan')->where('rute_id', $r)->select('id')))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->whereIn('pelanggan_id', DB::table('pelanggan')
                ->where(fn ($w) => $w->where('nama', 'like', "%{$t}%")->orWhere('idpel', 'like', "{$t}%"))->select('id')))
            ->latest('dikirim_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PencatatanMeter $c) => [
                'id' => $c->id,
                'pelanggan' => ['idpel' => $c->pelanggan->idpel, 'nama' => $c->pelanggan->nama, 'rute' => $c->pelanggan->rute?->kode],
                'biller' => $c->biller->name,
                'jenis' => $c->jenis->value,
                'stand_kini' => $c->stand_kini,
                'pemakaian_kwh' => $c->pemakaian_kwh,
                'sisa_kwh' => $c->sisa_kwh,
                'status' => $c->status->value,
                'anomali' => $c->anomali->first()?->jenis->value,
                'jumlah_revisi' => $c->jumlah_revisi,
                'dikirim_at' => $c->dikirim_at?->toIso8601String(),
            ]);

        return Inertia::render('DataLapangan/Pencatatan', [
            'catatan' => $catatan,
            'filters' => (object) ($filters + ['periode' => $periode?->kode]),
            'periodeOptions' => Periode::orderByDesc('kode')->get()->map(fn (Periode $p) => ['value' => $p->kode, 'label' => $p->nama()]),
            'ruteOptions' => $this->ruteOptions($unitId),
        ]);
    }

    public function pelanggan(Request $request): Response
    {
        $unitId = $request->user()->unit_id;
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(StatusPelanggan::class)],
            'rute_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $pelanggan = Pelanggan::query()
            ->whereIn('rute_id', Rute::where('unit_id', $unitId)->select('id'))
            ->with('rute:id,kode')
            ->withCount(['invoice as lembar_menunggak' => fn ($q) => $q->where('status', StatusInvoice::Menunggak)])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['rute_id'] ?? null, fn ($q, $r) => $q->where('rute_id', $r))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$t}%")
                ->orWhere('idpel', 'like', "{$t}%")->orWhere('alamat', 'like', "%{$t}%")))
            ->orderBy('rute_id')->orderBy('urutan_rute')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Pelanggan $p) => [
                'id' => $p->id,
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => $p->alamat,
                'rute' => $p->rute->kode,
                'urutan' => $p->urutan_rute,
                'tarif' => $p->labelTarif(),
                'jenis_label' => $p->jenis->label(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'lembar_menunggak' => $p->lembar_menunggak,
            ]);

        return Inertia::render('DataLapangan/Pelanggan', [
            'pelanggan' => $pelanggan,
            'filters' => (object) $filters,
            'statusOptions' => StatusPelanggan::options(),
            'ruteOptions' => $this->ruteOptions($unitId),
        ]);
    }

    public function rute(Request $request): Response
    {
        $unitId = $request->user()->unit_id;
        $periode = Periode::berjalan();

        $terkirim = $periode
            ? PencatatanMeter::where('pencatatan_meter.periode_id', $periode->id)->where('pencatatan_meter.status', '!=', StatusCatatan::Draft)
                ->join('penugasan', 'penugasan.id', '=', 'pencatatan_meter.penugasan_id')
                ->groupBy('penugasan.rute_id')->selectRaw('penugasan.rute_id, COUNT(*) as n')->pluck('n', 'rute_id')
            : collect();
        $penugasan = $periode ? Penugasan::where('periode_id', $periode->id)->with('biller:id,name')->get()->keyBy('rute_id') : collect();

        $rute = Rute::query()
            ->where('unit_id', $unitId)
            ->with('biller:id,name')
            ->withCount(['pelanggan as pelanggan_aktif' => fn ($q) => $q->where('status', StatusPelanggan::Aktif)])
            ->orderBy('kode')
            ->get()
            ->map(fn (Rute $r) => [
                'id' => $r->id,
                'kode' => $r->kode,
                'nama' => $r->nama,
                'kelurahan' => $r->kelurahan,
                'is_active' => $r->is_active,
                'pemegang' => $r->biller?->name,
                'pelanggan_aktif' => $r->pelanggan_aktif,
                'penugasan' => ($t = $penugasan->get($r->id)) ? [
                    'biller' => $t->biller->name,
                    'target' => $t->target_pelanggan,
                    'terkirim' => (int) ($terkirim[$r->id] ?? 0),
                ] : null,
            ]);

        return Inertia::render('DataLapangan/Rute', ['rute' => $rute, 'periode' => $periode ? ['kode' => $periode->kode, 'nama' => $periode->nama()] : null]);
    }

    public function biller(Request $request, KinerjaReport $report): Response
    {
        $unitId = $request->user()->unit_id;
        $periodeFinal = Periode::where('status', 'ditutup')->orderByDesc('kode')->first();
        $skor = $periodeFinal ? SkorKinerja::where('periode_id', $periodeFinal->id)->get()->keyBy('biller_id') : collect();
        $berjalan = Periode::berjalan();

        $biller = User::query()
            ->where('role', Role::Biller)
            ->where('unit_id', $unitId)
            ->with('rute:id,kode,biller_id')
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($skor, $berjalan) {
                $s = $skor->get($u->id);
                $target = $berjalan ? (int) Penugasan::where('periode_id', $berjalan->id)->where('biller_id', $u->id)->sum('target_pelanggan') : 0;
                $terkirim = $berjalan ? PencatatanMeter::where('periode_id', $berjalan->id)->where('biller_id', $u->id)->where('status', '!=', StatusCatatan::Draft)->count() : 0;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'nip' => $u->nip,
                    'phone' => $u->phone,
                    'foto_url' => $u->fotoProfilUrl(),
                    'is_active' => $u->is_active,
                    'rute' => $u->rute->pluck('kode')->all(),
                    'target' => $target,
                    'terkirim' => $terkirim,
                    'skor' => $s?->skor,
                    'peringkat' => $s?->peringkat,
                    'status' => $s ? KinerjaReport::status($s->skor, true) : null,
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                ];
            });

        return Inertia::render('DataLapangan/Biller', [
            'biller' => $biller,
            'periodeFinal' => $periodeFinal?->nama(),
            'periodeBerjalan' => $berjalan?->nama(),
        ]);
    }

    private function ruteOptions(?int $unitId): array
    {
        return Rute::where('unit_id', $unitId)->orderBy('kode')->get(['id', 'kode', 'nama'])
            ->map(fn (Rute $r) => ['value' => $r->id, 'label' => "{$r->kode} · {$r->nama}"])->all();
    }
}
