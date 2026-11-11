<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\JenisPelanggan;
use App\Enums\StatusPelanggan;
use App\Exports\TemplatePelangganExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\ImporPelangganRequest;
use App\Http\Requests\Pengaturan\PelangganRequest;
use App\Jobs\ImporPelanggan;
use App\Models\Pelanggan;
use App\Models\Rute;
use App\Models\Tarif;
use App\Support\Opsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PelangganController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Pelanggan::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'unit_id' => ['nullable', 'integer'],
            'rute_id' => ['nullable', 'integer'],
            'jenis' => ['nullable', Rule::enum(JenisPelanggan::class)],
            'status' => ['nullable', Rule::enum(StatusPelanggan::class)],
        ]);

        $pelanggan = Pelanggan::query()
            ->with('rute:id,kode,nama,unit_id')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('idpel', 'like', "{$term}%")
                ->orWhere('nomor_meter', 'like', "{$term}%")
                ->orWhere('nama', 'like', "%{$term}%")
                ->orWhere('alamat', 'like', "%{$term}%")))
            ->when($filters['unit_id'] ?? null, fn ($q, $unit) => $q->whereIn('rute_id', Rute::select('id')->where('unit_id', $unit)))
            ->when($filters['rute_id'] ?? null, fn ($q, $rute) => $q->where('rute_id', $rute))
            ->when($filters['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('rute_id')
            ->orderBy('urutan_rute')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Pelanggan $p) => [
                'id' => $p->id,
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => $p->alamat,
                'kelurahan' => $p->kelurahan,
                'rute' => $p->rute->kode,
                'urutan_rute' => $p->urutan_rute,
                'jenis' => $p->jenis->value,
                'jenis_label' => $p->jenis->label(),
                'tarif' => $p->labelTarif(),
                'nomor_meter' => $p->nomor_meter,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'punya_koordinat' => $p->latitude !== null,
            ]);

        return Inertia::render('Pengaturan/Pelanggan/Index', [
            'pelanggan' => $pelanggan,
            'filters' => (object) $filters,
            'options' => [
                'units' => Opsi::unit(),
                'rute' => Opsi::rute(),
                'jenis' => JenisPelanggan::options(),
                'status' => StatusPelanggan::options(),
            ],
            'total' => Pelanggan::count(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Pelanggan::class);

        return $this->form(null);
    }

    public function store(PelangganRequest $request): RedirectResponse
    {
        $pelanggan = Pelanggan::create($request->validated());

        return to_route('pengaturan.pelanggan.index', ['rute_id' => $pelanggan->rute_id])
            ->with('success', "Pelanggan {$pelanggan->nama} ({$pelanggan->idpel}) ditambahkan.");
    }

    public function edit(Pelanggan $pelanggan): Response
    {
        $this->authorize('update', $pelanggan);

        return $this->form($pelanggan);
    }

    public function update(PelangganRequest $request, Pelanggan $pelanggan): RedirectResponse
    {
        $pelanggan->update($request->validated());

        return to_route('pengaturan.pelanggan.index', ['rute_id' => $pelanggan->rute_id])
            ->with('success', "Data {$pelanggan->nama} ({$pelanggan->idpel}) diperbarui.");
    }

    public function destroy(Pelanggan $pelanggan): RedirectResponse
    {
        $this->authorize('delete', $pelanggan);

        $pelanggan->delete();

        return back()->with('success', "Pelanggan {$pelanggan->idpel} dihapus.");
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Pelanggan::class);

        return Excel::download(new TemplatePelangganExport, 'template-impor-pelanggan.xlsx');
    }

    /**
     * Impor dijalankan langsung (sinkron) agar hasilnya bisa ditampilkan.
     * Semua baris divalidasi dulu; bila ada yang salah, tidak ada yang disimpan.
     */
    public function impor(ImporPelangganRequest $request): RedirectResponse
    {
        $path = $request->file('file')->store('impor', 'local');

        $hasil = (new ImporPelanggan($path, $request->integer('unit_id')))->handle();

        if ($hasil['errors']) {
            return back()->with('impor', $hasil)->with('error', 'Impor dibatalkan. Perbaiki baris yang bermasalah lalu unggah ulang.');
        }

        return back()->with('impor', $hasil)->with(
            'success',
            "Impor selesai: {$hasil['dibuat']} pelanggan baru, {$hasil['diperbarui']} diperbarui.",
        );
    }

    private function form(?Pelanggan $pelanggan): Response
    {
        $urutanBerikutnya = Pelanggan::withTrashed()
            ->selectRaw('rute_id, MAX(urutan_rute) + 1 as berikutnya')
            ->groupBy('rute_id')
            ->pluck('berikutnya', 'rute_id');

        return Inertia::render('Pengaturan/Pelanggan/Form', [
            'pelanggan' => $pelanggan ? [
                ...$pelanggan->only([
                    'id', 'idpel', 'rute_id', 'urutan_rute', 'nama', 'alamat', 'kelurahan', 'golongan_tarif',
                    'daya_va', 'nomor_meter', 'letak_meter', 'latitude', 'longitude',
                ]),
                'jenis' => $pelanggan->jenis->value,
                'status' => $pelanggan->status->value,
            ] : null,
            'options' => [
                'units' => Opsi::unit(),
                'rute' => Opsi::rute(),
                'jenis' => JenisPelanggan::options(),
                'status' => StatusPelanggan::options(),
                'golongan' => Tarif::query()->distinct()->orderBy('golongan')->pluck('golongan'),
                'urutan_berikutnya' => (object) $urutanBerikutnya,
            ],
        ]);
    }
}
