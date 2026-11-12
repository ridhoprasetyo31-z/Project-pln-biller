<?php

namespace App\Http\Controllers;

use App\Enums\JenisPelanggan;
use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\PencatatanMeter;
use App\Models\Periode;
use App\Models\Rute;
use App\Services\InvoiceGenerator;
use App\Support\StatusCatatanView;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Daftar dan detail invoice untuk penagihan. Biller melihat invoice pelanggan
 * di rutenya; koordinator melihat semua invoice di unitnya.
 */
class PenagihanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:tagih,belum_bayar,menunggak,lunas,semua'],
            'periode_id' => ['nullable', 'integer'],
            'rute_id' => ['nullable', 'integer'],
        ]);
        $status = $filters['status'] ?? 'tagih';

        $dasar = fn () => Invoice::query()->terlihatOleh($user);

        $invoice = $dasar()
            ->with([
                'pelanggan:id,idpel,nama,alamat,rute_id,urutan_rute',
                'pelanggan.rute:id,kode',
                'periode:id,kode',
                'kunjunganTerakhir',
            ])
            ->when($status === 'tagih', fn ($q) => $q->whereIn('status', [StatusInvoice::BelumBayar, StatusInvoice::Menunggak]))
            ->when(in_array($status, ['belum_bayar', 'menunggak', 'lunas'], true), fn ($q) => $q->where('status', $status))
            ->when($filters['periode_id'] ?? null, fn ($q, $id) => $q->where('periode_id', $id))
            ->when($filters['rute_id'] ?? null, fn ($q, $id) => $q->whereIn('pelanggan_id', DB::table('pelanggan')->where('rute_id', $id)->select('id')))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('nomor', 'like', "%{$term}%")
                ->orWhereIn('pelanggan_id', DB::table('pelanggan')
                    ->where('nama', 'like', "%{$term}%")
                    ->orWhere('idpel', 'like', "{$term}%")
                    ->select('id'))))
            ->orderByRaw("FIELD(status, 'menunggak', 'belum_bayar', 'lunas', 'dibatalkan')")
            ->orderBy('jatuh_tempo')
            ->paginate(25)
            ->withQueryString();

        // Jumlah lembar menunggak per pelanggan (bekal TUSBUG).
        $lembar = Invoice::query()
            ->whereIn('pelanggan_id', $invoice->pluck('pelanggan_id'))
            ->where('status', StatusInvoice::Menunggak)
            ->groupBy('pelanggan_id')
            ->selectRaw('pelanggan_id, COUNT(*) as n')
            ->pluck('n', 'pelanggan_id');

        $invoice->through(fn (Invoice $i) => [
            'id' => $i->id,
            'nomor' => $i->nomor,
            'periode' => $i->periode->kode,
            'pelanggan' => [
                'idpel' => $i->pelanggan->idpel,
                'nama' => $i->pelanggan->nama,
                'alamat' => $i->pelanggan->alamat,
                'rute' => $i->pelanggan->rute?->kode,
            ],
            'pemakaian_kwh' => $i->pemakaian_kwh,
            'total' => $i->total,
            'jatuh_tempo' => $i->jatuh_tempo->toDateString(),
            'status' => $i->status->value,
            'status_label' => $i->status->label(),
            'dibayar_at' => $i->dibayar_at?->toIso8601String(),
            'lembar_menunggak' => (int) ($lembar[$i->pelanggan_id] ?? 0),
            'kunjungan' => $i->kunjunganTerakhir ? [
                'hasil_label' => $i->kunjunganTerakhir->hasil->label(),
                'status' => $i->kunjunganTerakhir->status->value,
                'waktu' => $i->kunjunganTerakhir->dikunjungi_at?->toIso8601String(),
            ] : null,
        ]);

        $ringkas = $dasar()
            ->selectRaw('status, COUNT(*) as n, SUM(total) as nominal')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($r) => $r->status instanceof StatusInvoice ? $r->status->value : $r->status);

        return Inertia::render('Penagihan/Index', [
            'invoice' => $invoice,
            'filters' => (object) ($filters + ['status' => $status]),
            'ringkasan' => [
                'belum_bayar' => ['jumlah' => (int) ($ringkas['belum_bayar']->n ?? 0), 'nominal' => (float) ($ringkas['belum_bayar']->nominal ?? 0)],
                'menunggak' => ['jumlah' => (int) ($ringkas['menunggak']->n ?? 0), 'nominal' => (float) ($ringkas['menunggak']->nominal ?? 0)],
                'lunas' => ['jumlah' => (int) ($ringkas['lunas']->n ?? 0), 'nominal' => (float) ($ringkas['lunas']->nominal ?? 0)],
            ],
            'periodeOptions' => Periode::query()->whereIn('id', $dasar()->select('periode_id'))->orderByDesc('kode')->get()
                ->map(fn (Periode $p) => ['value' => $p->id, 'label' => $p->nama()]),
            'ruteOptions' => Rute::query()
                ->when($user->role === Role::KoordinatorAdmin, fn ($q) => $q->where('unit_id', $user->unit_id))
                ->when($user->role === Role::Biller, fn ($q) => $q->whereIn('id', DB::table('penugasan')
                    ->join('periode', 'periode.id', '=', 'penugasan.periode_id')
                    ->where('periode.status', 'berjalan')->where('biller_id', $user->id)->select('rute_id')))
                ->orderBy('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Rute $r) => ['value' => $r->id, 'label' => "{$r->kode} · {$r->nama}"]),
            'can' => ['susulan' => $user->can('buatPeriode', Invoice::class)],
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $user = $request->user();

        $invoice->load([
            'pelanggan.rute:id,kode,nama',
            'periode',
            'tarif',
            'pencatatanMeter.lampiran',
            'pencatatanMeter.biller:id,name',
            'kunjungan' => fn ($q) => $q->with('biller:id,name', 'lampiran', 'verifikasiTerakhir')->latest('dikunjungi_at'),
            'koreksi' => fn ($q) => $q->with('pengaju:id,name')->latest(),
        ]);
        $c = $invoice->pencatatanMeter;
        $p = $invoice->pelanggan;

        return Inertia::render('Penagihan/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'nomor' => $invoice->nomor,
                'periode' => $invoice->periode->nama(),
                'periode_kode' => $invoice->periode->kode,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'jatuh_tempo' => $invoice->jatuh_tempo->toDateString(),
                'dibayar_at' => $invoice->dibayar_at?->toIso8601String(),
                'pemakaian_kwh' => $invoice->pemakaian_kwh,
                'harga_per_kwh' => $invoice->harga_per_kwh,
                'biaya_pemakaian' => $invoice->biayaPemakaian(),
                'biaya_beban' => $invoice->biaya_beban,
                'pajak' => $invoice->pajak,
                'ppj_persen' => InvoiceGenerator::ppjDariInvoice($invoice),
                'total' => $invoice->total,
                'golongan' => $invoice->tarif?->golongan,
            ],
            'pelanggan' => [
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => "{$p->alamat}, {$p->kelurahan}",
                'tarif' => $p->labelTarif(),
                'nomor_meter' => $p->nomor_meter,
                'rute' => $p->rute?->kode,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'status' => $p->status->label(),
            ],
            'pencatatan' => $c ? [
                'stand_lalu' => $c->stand_lalu,
                'stand_kini' => $c->stand_kini,
                'biller' => $c->biller?->name,
                'disetujui_at' => $c->disetujui_at?->toIso8601String(),
                'foto' => StatusCatatanView::foto(StatusCatatanView::fotoUtama($c)),
            ] : null,
            'kunjungan' => $invoice->kunjungan->map(fn (KunjunganPenagihan $k) => [
                'id' => $k->id,
                'biller' => $k->biller->name,
                'hasil_label' => $k->hasil->label(),
                'tanggal_janji' => $k->tanggal_janji?->toDateString(),
                'nominal_dibayar' => $k->nominal_dibayar,
                'keterangan' => $k->keterangan,
                'jarak_m' => $k->jarak_m,
                'waktu' => $k->dikunjungi_at?->toIso8601String(),
                'status' => $k->status->value,
                'foto' => StatusCatatanView::foto($k->lampiran->first()),
                'verifikasi' => StatusCatatanView::verifikasi($k->verifikasiTerakhir),
                'bisa_diubah' => $user->can('update', $k),
            ]),
            'koreksi' => $invoice->koreksi->map(fn (KoreksiRekening $k) => [
                'id' => $k->id,
                'pengaju' => $k->pengaju->name,
                'penyebab_label' => $k->penyebab->label(),
                'stand_lama' => $k->stand_lama,
                'stand_baru' => $k->stand_baru,
                'tagihan_lama' => $k->tagihan_lama,
                'tagihan_baru' => $k->tagihan_baru,
                'status' => $k->status->value,
                'waktu' => $k->created_at->toIso8601String(),
            ]),
            'can' => [
                'kunjungan' => $user->can('create', [KunjunganPenagihan::class, $invoice]),
                'koreksi' => $user->can('create', [KoreksiRekening::class, $invoice])
                    && ! $invoice->koreksi->contains(fn ($k) => in_array($k->status, [StatusCatatan::MenungguVerifikasi, StatusCatatan::Ditandai, StatusCatatan::Revisi], true)),
                'lunas' => $user->can('tandaiLunas', $invoice),
            ],
        ]);
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        $this->authorize('view', $invoice);
        $invoice->load('pelanggan.rute', 'periode', 'tarif', 'pencatatanMeter');

        return Pdf::loadView('invoice.rekening', [
            'invoice' => $invoice,
            'ppj' => InvoiceGenerator::ppjDariInvoice($invoice),
            'unit' => $invoice->pelanggan->rute?->unit()->value('nama'),
        ])
            ->setPaper('a5', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->download(str_replace('/', '-', $invoice->nomor).'.pdf');
    }

    /** Koordinator menandai lunas (pembayaran di loket, bank, atau aplikasi). */
    public function lunas(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('tandaiLunas', $invoice);

        $data = $request->validate(
            ['dibayar_at' => ['required', 'date', 'before_or_equal:today']],
            ['dibayar_at.before_or_equal' => 'Tanggal bayar tidak boleh setelah hari ini.'],
            ['dibayar_at' => 'tanggal bayar'],
        );

        $invoice->update(['status' => StatusInvoice::Lunas, 'dibayar_at' => CarbonImmutable::parse($data['dibayar_at'])->setTimeFrom(now())]);

        activity('penagihan')->performedOn($invoice)->causedBy($request->user())->event('lunas')->log('Invoice ditandai lunas');

        return back()->with('success', "Invoice {$invoice->nomor} ditandai lunas.");
    }

    /** Susulkan invoice untuk catatan pascabayar disetujui yang belum punya invoice. */
    public function susulan(Request $request, InvoiceGenerator $generator): RedirectResponse
    {
        $this->authorize('buatPeriode', Invoice::class);

        $periodeIds = PencatatanMeter::query()
            ->diUnit($request->user()->unit_id)
            ->where('jenis', JenisPelanggan::Pascabayar)
            ->where('status', StatusCatatan::Disetujui)
            ->whereDoesntHave('invoice')
            ->distinct()
            ->pluck('periode_id');

        $dibuat = 0;
        foreach (Periode::whereIn('id', $periodeIds)->get() as $periode) {
            $dibuat += $generator->buatPeriode($periode)['dibuat'];
        }

        return back()->with('success', $dibuat ? "{$dibuat} invoice baru diterbitkan." : 'Semua catatan disetujui sudah punya invoice.');
    }
}
