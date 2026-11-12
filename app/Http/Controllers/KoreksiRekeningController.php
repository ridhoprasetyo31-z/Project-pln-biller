<?php

namespace App\Http\Controllers;

use App\Enums\PenyebabKoreksi;
use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Http\Requests\KoreksiRequest;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Services\InvoiceGenerator;
use App\Services\KoreksiService;
use App\Support\StatusCatatanView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Koreksi rekening: daftar, pengajuan oleh biller, detail, dan perbaikan setelah revisi.
 */
class KoreksiRekeningController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', KoreksiRekening::class);
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(StatusCatatan::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $koreksi = KoreksiRekening::query()
            ->with('pelanggan:id,idpel,nama', 'periode:id,kode', 'pengaju:id,name', 'anomali:id,anomaliable_id,anomaliable_type,jenis')
            ->when($user->role === Role::Biller, fn ($q) => $q->where('diajukan_oleh', $user->id))
            ->when($user->role === Role::KoordinatorAdmin, fn ($q) => $q->diUnit($user->unit_id))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('pelanggan', fn ($p) => $p->where(fn ($w) => $w
                ->where('nama', 'like', "%{$term}%")->orWhere('idpel', 'like', "{$term}%"))))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (KoreksiRekening $k) => [
                'id' => $k->id,
                'pelanggan' => $k->pelanggan->only(['idpel', 'nama']),
                'periode' => $k->periode->kode,
                'pengaju' => $k->pengaju->name,
                'stand_lama' => $k->stand_lama,
                'stand_baru' => $k->stand_baru,
                'tagihan_lama' => $k->tagihan_lama,
                'tagihan_baru' => $k->tagihan_baru,
                'penyebab_label' => $k->penyebab->label(),
                'status' => $k->status->value,
                'waktu' => $k->created_at->toIso8601String(),
            ]);

        return Inertia::render('Koreksi/Index', [
            'koreksi' => $koreksi,
            'filters' => (object) $filters,
            'bisaAjukan' => $user->role === Role::Biller,
        ]);
    }

    /** Form pengajuan dari ?invoice=ID atau ?idpel=ID pelanggan (invoice terbaru yang belum lunas). */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $invoice = $request->filled('invoice')
            ? Invoice::find($request->integer('invoice'))
            : Invoice::query()
                ->terlihatOleh($user)
                ->whereIn('status', [StatusInvoice::BelumBayar, StatusInvoice::Menunggak])
                ->whereHas('pelanggan', fn ($q) => $q->where('idpel', $request->string('idpel')->trim()->toString()))
                ->latest('periode_id')
                ->first();

        if (! $invoice) {
            return back()->with('error', 'Invoice yang belum lunas untuk ID pelanggan tersebut tidak ditemukan di rute Anda.');
        }

        $this->authorize('create', [KoreksiRekening::class, $invoice]);

        return $this->form($invoice, null);
    }

    public function store(KoreksiRequest $request, KoreksiService $service): RedirectResponse
    {
        $koreksi = $service->ajukan($request->user(), $request->invoice(), $request->safe()->except(['foto', 'invoice_id']), $request->file('foto'));

        return to_route('koreksi-rekening.show', $koreksi)->with('success', 'Koreksi rekening terkirim ke koordinator untuk diverifikasi.');
    }

    public function show(KoreksiRekening $koreksi): Response
    {
        $this->authorize('view', $koreksi);
        $koreksi->load('pelanggan', 'periode', 'invoice', 'pengaju:id,name', 'pencatatanMeter.biller:id,name', 'pencatatanMeter.lampiran', 'lampiran', 'verifikasi.verifikator:id,name');

        return Inertia::render('Koreksi/Show', [
            'koreksi' => [
                'id' => $koreksi->id,
                'status' => $koreksi->status->value,
                'pelanggan' => [
                    'idpel' => $koreksi->pelanggan->idpel,
                    'nama' => $koreksi->pelanggan->nama,
                    'alamat' => "{$koreksi->pelanggan->alamat}, {$koreksi->pelanggan->kelurahan}",
                    'tarif' => $koreksi->pelanggan->labelTarif(),
                ],
                'periode' => $koreksi->periode->nama(),
                'invoice' => $koreksi->invoice ? ['id' => $koreksi->invoice->id, 'nomor' => $koreksi->invoice->nomor, 'status_label' => $koreksi->invoice->status->label()] : null,
                'pengaju' => $koreksi->pengaju->name,
                'pencatat' => $koreksi->pencatatanMeter?->biller?->name,
                'penyebab_label' => $koreksi->penyebab->label(),
                'kurangi_akurasi' => $koreksi->penyebab === PenyebabKoreksi::SalahCatatBiller,
                'alasan' => $koreksi->alasan,
                'stand_lalu' => $koreksi->pencatatanMeter?->stand_lalu,
                'stand_lama' => $koreksi->stand_lama,
                'stand_baru' => $koreksi->stand_baru,
                'kwh_lama' => $koreksi->kwh_lama,
                'kwh_baru' => $koreksi->kwh_baru,
                'tagihan_lama' => $koreksi->tagihan_lama,
                'tagihan_baru' => $koreksi->tagihan_baru,
                'waktu' => $koreksi->created_at->toIso8601String(),
                'jumlah_revisi' => $koreksi->jumlah_revisi,
                'foto' => StatusCatatanView::foto($koreksi->lampiran->first()),
                'foto_asli' => $koreksi->pencatatanMeter ? StatusCatatanView::foto(StatusCatatanView::fotoUtama($koreksi->pencatatanMeter)) : null,
                'riwayat' => $koreksi->verifikasi->map(fn ($v) => StatusCatatanView::verifikasi($v))->values(),
            ],
            'bisaDiubah' => request()->user()->can('update', $koreksi),
        ]);
    }

    public function edit(KoreksiRekening $koreksi): Response
    {
        $this->authorize('update', $koreksi);

        return $this->form($koreksi->invoice()->firstOrFail(), $koreksi);
    }

    public function update(KoreksiRequest $request, KoreksiRekening $koreksi, KoreksiService $service): RedirectResponse
    {
        $service->ajukan($request->user(), $request->invoice(), $request->safe()->except(['foto', 'invoice_id']), $request->file('foto'), $koreksi);

        return to_route('koreksi-rekening.show', $koreksi)->with('success', 'Perbaikan koreksi terkirim ke koordinator.');
    }

    private function form(Invoice $invoice, ?KoreksiRekening $koreksi): Response
    {
        $invoice->load('pelanggan', 'periode', 'pencatatanMeter.lampiran');
        $koreksi?->load('lampiran', 'verifikasiTerakhir.verifikator:id,name');
        $c = $invoice->pencatatanMeter;

        return Inertia::render('Koreksi/Form', [
            'invoice' => [
                'id' => $invoice->id,
                'nomor' => $invoice->nomor,
                'periode' => $invoice->periode->nama(),
                'status_label' => $invoice->status->label(),
                'pemakaian_kwh' => $invoice->pemakaian_kwh,
                'harga_per_kwh' => $invoice->harga_per_kwh,
                'biaya_beban' => $invoice->biaya_beban,
                'ppj_persen' => InvoiceGenerator::ppjDariInvoice($invoice),
                'total' => $invoice->total,
            ],
            'pelanggan' => [
                'idpel' => $invoice->pelanggan->idpel,
                'nama' => $invoice->pelanggan->nama,
                'alamat' => "{$invoice->pelanggan->alamat}, {$invoice->pelanggan->kelurahan}",
                'tarif' => $invoice->pelanggan->labelTarif(),
            ],
            'pencatatan' => [
                'stand_lalu' => $c?->stand_lalu,
                'stand_kini' => $c?->stand_kini,
                'foto' => $c ? StatusCatatanView::foto(StatusCatatanView::fotoUtama($c)) : null,
            ],
            'koreksi' => $koreksi ? [
                'id' => $koreksi->id,
                'stand_baru' => $koreksi->stand_baru,
                'penyebab' => $koreksi->penyebab->value,
                'alasan' => $koreksi->alasan,
                'foto' => StatusCatatanView::foto($koreksi->lampiran->first()),
                'verifikasi' => StatusCatatanView::verifikasi($koreksi->verifikasiTerakhir),
                'jumlah_revisi' => $koreksi->jumlah_revisi,
            ] : null,
            'penyebabOptions' => PenyebabKoreksi::options(),
        ]);
    }
}
