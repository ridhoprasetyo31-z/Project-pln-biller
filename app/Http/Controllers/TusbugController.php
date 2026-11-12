<?php

namespace App\Http\Controllers;

use App\Enums\HasilTusbug;
use App\Enums\Role;
use App\Enums\StatusTusbug;
use App\Http\Requests\TusbugLaksanakanRequest;
use App\Models\Pengaturan;
use App\Models\Tusbug;
use App\Models\User;
use App\Services\TusbugService;
use App\Support\StatusCatatanView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TUSBUG: koordinator menugaskan pemutusan dari daftar saran tunggakan,
 * biller melaksanakan dan melaporkan hasilnya.
 */
class TusbugController extends Controller
{
    public function __construct(private readonly TusbugService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tusbug::class);
        $user = $request->user();
        $koordinator = $user->role === Role::KoordinatorAdmin;
        $tab = $request->input('tab', $koordinator ? 'saran' : 'tugas');

        $dasar = fn (): Builder => Tusbug::query()
            ->with('pelanggan:id,idpel,nama,alamat,status,rute_id', 'pelanggan.rute:id,kode', 'biller:id,name')
            ->when($koordinator, fn ($q) => $q->diUnit($user->unit_id))
            ->when(! $koordinator, fn ($q) => $q->where('biller_id', $user->id));

        $ringkas = fn (Tusbug $t) => [
            'id' => $t->id,
            'nomor_surat' => $t->nomor_surat,
            'pelanggan' => [
                'idpel' => $t->pelanggan->idpel,
                'nama' => $t->pelanggan->nama,
                'alamat' => $t->pelanggan->alamat,
                'rute' => $t->pelanggan->rute?->kode,
                'status_label' => $t->pelanggan->status->label(),
            ],
            'biller' => $t->biller->name,
            'jumlah_lembar' => $t->jumlah_lembar,
            'total_tunggakan' => $t->total_tunggakan,
            'tanggal_rencana' => $t->tanggal_rencana->toDateString(),
            'status' => $t->status->value,
            'hasil_label' => $t->hasil?->label(),
            'dilaksanakan_at' => $t->dilaksanakan_at?->toIso8601String(),
        ];

        $berjalan = $dasar()->terbuka()
            ->orderByRaw("FIELD(status, 'revisi', 'ditugaskan', 'ditandai', 'menunggu_verifikasi')")
            ->orderBy('tanggal_rencana')
            ->get()->map($ringkas);
        $selesai = $dasar()->where('status', StatusTusbug::Disetujui)->latest('updated_at')->limit(50)->get()->map($ringkas);

        return Inertia::render('Tusbug/Index', [
            'tab' => $tab,
            'koordinator' => $koordinator,
            'saran' => $koordinator ? $this->service->saran($user->unit_id) : [],
            'berjalan' => $berjalan,
            'selesai' => $selesai,
            'minLembar' => (int) Pengaturan::ambil('min_lembar_tusbug'),
            'billerOptions' => $koordinator
                ? User::where('role', Role::Biller)->where('unit_id', $user->unit_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Tusbug::class);

        $data = $request->validate([
            'pelanggan_ids' => ['required', 'array', 'min:1', 'max:200'],
            'pelanggan_ids.*' => ['integer'],
            'biller_id' => ['nullable', 'integer'],
            'tanggal_rencana' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'pelanggan_ids.required' => 'Pilih minimal satu pelanggan.',
            'tanggal_rencana.after_or_equal' => 'Tanggal rencana tidak boleh sebelum hari ini.',
        ], ['tanggal_rencana' => 'tanggal rencana']);

        $tugas = $this->service->tugaskan($request->user(), $data['pelanggan_ids'], $data['biller_id'] ?? null, $data['tanggal_rencana']);

        return to_route('tusbug.index', ['tab' => 'berjalan'])
            ->with('success', "{$tugas->count()} tugas TUSBUG dibuat. Biller sudah menerima notifikasi.");
    }

    public function show(Request $request, Tusbug $tusbug): Response
    {
        $this->authorize('view', $tusbug);
        $tusbug->load('pelanggan.rute:id,kode,nama', 'biller:id,name,nip', 'penugas:id,name', 'invoice.periode:id,kode', 'lampiran', 'anomali', 'verifikasi.verifikator:id,name');
        $p = $tusbug->pelanggan;

        return Inertia::render('Tusbug/Show', [
            'tusbug' => [
                'id' => $tusbug->id,
                'nomor_surat' => $tusbug->nomor_surat,
                'status' => $tusbug->status->value,
                'biller' => $tusbug->biller->name,
                'penugas' => $tusbug->penugas->name,
                'tanggal_rencana' => $tusbug->tanggal_rencana->toDateString(),
                'jumlah_lembar' => $tusbug->jumlah_lembar,
                'total_tunggakan' => $tusbug->total_tunggakan,
                'hasil' => $tusbug->hasil?->value,
                'hasil_label' => $tusbug->hasil?->label(),
                'keterangan' => $tusbug->keterangan,
                'jarak_m' => $tusbug->jarak_m,
                'dilaksanakan_at' => $tusbug->dilaksanakan_at?->toIso8601String(),
                'jumlah_revisi' => $tusbug->jumlah_revisi,
                'invoice' => $tusbug->invoice->map(fn ($i) => [
                    'id' => $i->id, 'nomor' => $i->nomor, 'periode' => $i->periode->kode, 'total' => $i->total,
                    'status' => $i->status->value, 'status_label' => $i->status->label(),
                ])->values(),
                'anomali' => StatusCatatanView::anomali($tusbug->anomali),
                'foto_sebelum' => StatusCatatanView::foto($tusbug->lampiran->firstWhere('jenis.value', 'sebelum')),
                'foto_sesudah' => StatusCatatanView::foto($tusbug->lampiran->firstWhere('jenis.value', 'sesudah')),
                'riwayat' => $tusbug->verifikasi->map(fn ($v) => StatusCatatanView::verifikasi($v))->values(),
            ],
            'pelanggan' => [
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => "{$p->alamat}, {$p->kelurahan}",
                'tarif' => $p->labelTarif(),
                'nomor_meter' => $p->nomor_meter,
                'letak_meter' => $p->letak_meter,
                'rute' => $p->rute?->kode,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ],
            'hasilOptions' => HasilTusbug::options(),
            'ambangJarak' => (float) Pengaturan::ambil('ambang_jarak_m'),
            'can' => [
                'laksanakan' => $request->user()->can('laksanakan', $tusbug),
                'batal' => $request->user()->can('delete', $tusbug),
            ],
        ]);
    }

    public function laksanakan(TusbugLaksanakanRequest $request, Tusbug $tusbug): RedirectResponse
    {
        $this->service->laksanakan(
            $request->user(),
            $tusbug,
            $request->safe()->except(['foto_sebelum', 'foto_sesudah']),
            ['sebelum' => $request->file('foto_sebelum'), 'sesudah' => $request->file('foto_sesudah')],
        );

        return to_route('tusbug.show', $tusbug)->with('success', 'Hasil TUSBUG terkirim ke koordinator untuk diverifikasi.');
    }

    public function destroy(Tusbug $tusbug): RedirectResponse
    {
        $this->authorize('delete', $tusbug);

        $tusbug->invoice()->detach();
        $tusbug->delete();

        return to_route('tusbug.index', ['tab' => 'berjalan'])->with('success', "Tugas {$tusbug->nomor_surat} dibatalkan.");
    }
}
