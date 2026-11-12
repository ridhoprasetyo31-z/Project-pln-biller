<?php

namespace App\Http\Controllers;

use App\Enums\JenisPdil;
use App\Enums\JenisPelanggan;
use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Http\Requests\PdilRequest;
use App\Models\Invoice;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Models\Pengaturan;
use App\Models\Rute;
use App\Models\Tarif;
use App\Policies\PdilPolicy;
use App\Services\PdilService;
use App\Support\StatusCatatanView;
use App\Verifikasi\PdilView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PDIL: pendataan pelanggan baru dan perubahan data pelanggan.
 */
class PdilController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Pdil::class);
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(StatusCatatan::class)],
            'jenis' => ['nullable', Rule::enum(JenisPdil::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $pdil = Pdil::query()
            ->with('rute:id,kode', 'biller:id,name', 'anomali:id,anomaliable_id,anomaliable_type,jenis')
            ->when($user->role === Role::Biller, fn ($q) => $q->where('biller_id', $user->id))
            ->when($user->role === Role::KoordinatorAdmin, fn ($q) => $q->diUnit($user->unit_id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['jenis'] ?? null, fn ($q, $j) => $q->where('jenis', $j))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$t}%")->orWhere('idpel', 'like', "{$t}%")))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Pdil $p) => [
                'id' => $p->id,
                'jenis' => $p->jenis->value,
                'jenis_label' => $p->jenis->label(),
                'nama' => $p->nama,
                'idpel' => $p->idpel,
                'alamat' => $p->alamat,
                'rute' => $p->rute->kode,
                'biller' => $p->biller->name,
                'status' => $p->status->value,
                'anomali' => $p->anomali->first()?->jenis->value,
                'waktu' => $p->updated_at->toIso8601String(),
            ]);

        return Inertia::render('Pdil/Index', [
            'pdil' => $pdil,
            'filters' => (object) $filters,
            'jenisOptions' => JenisPdil::options(),
            'bisaAjukan' => $user->role === Role::Biller,
        ]);
    }

    /** ?jenis=pelanggan_baru, atau ?pelanggan=ID / ?idpel=ID untuk perubahan data. */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $pelanggan = null;

        if ($request->filled('pelanggan') || $request->filled('idpel')) {
            $pelanggan = Pelanggan::query()
                ->whereIn('id', Invoice::pelangganBiller($user))
                ->when($request->filled('pelanggan'), fn ($q) => $q->whereKey($request->integer('pelanggan')))
                ->when($request->filled('idpel'), fn ($q) => $q->where('idpel', $request->string('idpel')->trim()->toString()))
                ->first();

            if (! $pelanggan) {
                return back()->with('error', 'Pelanggan tersebut tidak ditemukan di rute Anda.');
            }
            $this->authorize('create', [Pdil::class, $pelanggan]);
        } else {
            $this->authorize('create', Pdil::class);
        }

        return $this->form($request, $pelanggan, null);
    }

    public function store(PdilRequest $request, PdilService $service): RedirectResponse
    {
        $pdil = $service->ajukan(
            $request->user(),
            $request->safe()->except(['foto_rumah', 'foto_meter']),
            ['rumah' => $request->file('foto_rumah'), 'meter' => $request->file('foto_meter')],
        );

        return to_route('pdil.show', $pdil)->with('success', 'PDIL terkirim ke koordinator untuk diverifikasi.');
    }

    public function show(Pdil $pdil): Response
    {
        $this->authorize('view', $pdil);
        $pdil->load('rute:id,kode,nama', 'biller:id,name', 'lampiran', 'anomali', 'verifikasi.verifikator:id,name');
        $lama = $pdil->jenis === JenisPdil::PerubahanData ? ($pdil->data_lama ?? []) : null;

        return Inertia::render('Pdil/Show', [
            'pdil' => [
                'id' => $pdil->id,
                'jenis' => $pdil->jenis->value,
                'jenis_label' => $pdil->jenis->label(),
                'status' => $pdil->status->value,
                'nama' => $pdil->nama,
                'idpel' => $pdil->idpel,
                'pelanggan_id' => $pdil->pelanggan_id,
                'rute' => "{$pdil->rute->kode} · {$pdil->rute->nama}",
                'biller' => $pdil->biller->name,
                'keterangan' => $pdil->keterangan,
                'latitude' => $pdil->latitude,
                'longitude' => $pdil->longitude,
                'waktu' => $pdil->updated_at->toIso8601String(),
                'jumlah_revisi' => $pdil->jumlah_revisi,
                'perbandingan' => PdilView::perbandingan($lama, $pdil->dataBaru()),
                'anomali' => StatusCatatanView::anomali($pdil->anomali),
                'foto_rumah' => StatusCatatanView::foto($pdil->lampiran->firstWhere('jenis.value', 'rumah')),
                'foto_meter' => StatusCatatanView::foto($pdil->lampiran->firstWhere('jenis.value', 'meter')),
                'riwayat' => $pdil->verifikasi->map(fn ($v) => StatusCatatanView::verifikasi($v))->values(),
            ],
            'bisaDiubah' => request()->user()->can('update', $pdil),
        ]);
    }

    public function edit(Request $request, Pdil $pdil): Response
    {
        $this->authorize('update', $pdil);

        return $this->form($request, $pdil->pelanggan_id ? Pelanggan::find($pdil->pelanggan_id) : null, $pdil);
    }

    public function update(PdilRequest $request, Pdil $pdil, PdilService $service): RedirectResponse
    {
        $service->ajukan(
            $request->user(),
            $request->safe()->except(['foto_rumah', 'foto_meter']),
            ['rumah' => $request->file('foto_rumah'), 'meter' => $request->file('foto_meter')],
            $pdil,
        );

        return to_route('pdil.show', $pdil)->with('success', 'Perbaikan PDIL terkirim ke koordinator.');
    }

    private function form(Request $request, ?Pelanggan $pelanggan, ?Pdil $pdil): Response
    {
        $pdil?->load('lampiran', 'verifikasiTerakhir.verifikator:id,name');
        $nilai = $pdil?->dataBaru() ?? ($pelanggan ? PdilService::dataPelanggan($pelanggan) : null);

        return Inertia::render('Pdil/Form', [
            'jenis' => $pdil?->jenis->value ?? ($pelanggan ? 'perubahan_data' : 'pelanggan_baru'),
            'pelanggan' => $pelanggan ? [
                'id' => $pelanggan->id,
                'idpel' => $pelanggan->idpel,
                'rute_id' => $pelanggan->rute_id,
                'data' => PdilService::dataPelanggan($pelanggan),
            ] : null,
            'pdil' => $pdil ? [
                'id' => $pdil->id,
                'rute_id' => $pdil->rute_id,
                'keterangan' => $pdil->keterangan,
                'foto_rumah' => StatusCatatanView::foto($pdil->lampiran->firstWhere('jenis.value', 'rumah')),
                'foto_meter' => StatusCatatanView::foto($pdil->lampiran->firstWhere('jenis.value', 'meter')),
                'verifikasi' => StatusCatatanView::verifikasi($pdil->verifikasiTerakhir),
            ] : null,
            'nilai' => $nilai,
            'ruteOptions' => Rute::whereIn('id', PdilPolicy::ruteBiller($request->user()))->orderBy('kode')->get(['id', 'kode', 'nama', 'kelurahan'])
                ->map(fn (Rute $r) => ['value' => $r->id, 'label' => "{$r->kode} · {$r->nama}", 'kelurahan' => $r->kelurahan]),
            'golonganOptions' => Tarif::query()->distinct()->orderBy('golongan')->pluck('golongan'),
            'jenisPelangganOptions' => JenisPelanggan::options(),
            'ambangJarak' => (float) Pengaturan::ambil('ambang_jarak_m'),
        ]);
    }
}
