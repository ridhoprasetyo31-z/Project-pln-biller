<?php

namespace App\Http\Controllers;

use App\Enums\AlasanTolak;
use App\Http\Requests\TolakCatatanRequest;
use App\Models\Pdil;
use App\Services\VerificationService;
use App\Verifikasi\SumberKoreksi;
use App\Verifikasi\SumberKunjungan;
use App\Verifikasi\SumberPdil;
use App\Verifikasi\SumberPencatatan;
use App\Verifikasi\SumberTusbug;
use App\Verifikasi\SumberVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satu antrean verifikasi untuk semua modul (PRD 3.8): catat meter,
 * kunjungan penagihan, dan koreksi rekening. Modul berikutnya cukup
 * menambah kelas SumberVerifikasi baru di daftar sumber().
 */
class VerifikasiController extends Controller
{
    private const PER_HALAMAN = 30;

    public function __construct(private readonly VerificationService $service) {}

    /** @return array<string, SumberVerifikasi> */
    private function sumber(): array
    {
        return collect([
            app(SumberPencatatan::class),
            app(SumberKunjungan::class),
            app(SumberKoreksi::class),
            app(SumberPdil::class),
            app(SumberTusbug::class),
        ])->keyBy(fn (SumberVerifikasi $s) => $s->tipe())->all();
    }

    public function index(Request $request): Response
    {
        $unitId = $request->user()->unit_id;
        $semua = $this->sumber();
        $jenis = $request->input('jenis');
        $aktif = isset($semua[$jenis]) ? [$jenis => $semua[$jenis]] : $semua;

        $jumlah = collect(['ditandai', 'normal', 'diperiksa'])
            ->mapWithKeys(fn ($tab) => [$tab => collect($aktif)->sum(fn (SumberVerifikasi $s) => $s->jumlah($unitId, $tab))])
            ->all();

        $tab = $request->input('tab');
        if (! in_array($tab, ['ditandai', 'normal', 'diperiksa'], true)) {
            $tab = $jumlah['ditandai'] > 0 ? 'ditandai' : 'normal';
        }

        $items = collect($aktif)
            ->flatMap(fn (SumberVerifikasi $s) => $s->items($unitId, $tab))
            ->sortBy('urut', SORT_REGULAR, $tab === 'diperiksa')
            ->values();

        $halaman = max(1, $request->integer('page', 1));
        $antrean = new LengthAwarePaginator(
            $items->forPage($halaman, self::PER_HALAMAN)->values(),
            $items->count(),
            self::PER_HALAMAN,
            $halaman,
            ['path' => route('verifikasi.index'), 'query' => $request->only(['tab', 'jenis'])],
        );

        $tipeDipilih = $request->input('tipe');
        $idDipilih = $request->integer('id');
        if (! $idDipilih || ! isset($semua[$tipeDipilih])) {
            $pertama = $antrean->first();
            $tipeDipilih = $pertama['tipe'] ?? null;
            $idDipilih = $pertama['id'] ?? null;
        }
        $model = $tipeDipilih ? $semua[$tipeDipilih]->temukan($unitId, (int) $idDipilih) : null;

        return Inertia::render('Verifikasi/Index', [
            'tab' => $tab,
            'jenis' => isset($semua[$jenis]) ? $jenis : null,
            'jumlah' => $jumlah,
            'jenisOptions' => collect($semua)->map(fn (SumberVerifikasi $s) => [
                'value' => $s->tipe(),
                'label' => $s->label(),
                'menunggu' => $s->jumlah($unitId, 'ditandai') + $s->jumlah($unitId, 'normal'),
            ])->values(),
            'antrean' => $antrean,
            'detail' => $model ? $semua[$tipeDipilih]->detail($model) : null,
            'alasanOptions' => AlasanTolak::options(),
        ]);
    }

    public function setujui(Request $request, string $tipe, int $id): RedirectResponse
    {
        $catatan = $this->temukan($request, $tipe, $id);
        $this->authorize('verifikasi', $catatan);

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
            'tab' => ['nullable', 'string'],
            'jenis' => ['nullable', 'string'],
        ]);
        $this->service->setujui($catatan, $request->user(), $data['catatan'] ?? null);

        return to_route('verifikasi.index', array_filter(['tab' => $data['tab'] ?? null, 'jenis' => $data['jenis'] ?? null]))
            ->with('success', "{$this->sumber()[$tipe]->label()} {$this->label($catatan)} disetujui.");
    }

    public function tolak(TolakCatatanRequest $request, string $tipe, int $id): RedirectResponse
    {
        $catatan = $this->temukan($request, $tipe, $id);
        $this->authorize('verifikasi', $catatan);

        $this->service->tolak(
            $catatan,
            $request->user(),
            AlasanTolak::from($request->validated('alasan')),
            $request->validated('catatan'),
        );

        return to_route('verifikasi.index', array_filter(['tab' => $request->validated('tab'), 'jenis' => $request->input('jenis')]))
            ->with('success', "{$this->sumber()[$tipe]->label()} {$this->label($catatan)} dikembalikan ke biller untuk revisi.");
    }

    private function temukan(Request $request, string $tipe, int $id): Model
    {
        $sumber = $this->sumber()[$tipe] ?? abort(404);
        $kelas = $sumber->query(null)->getModel()::class;

        // Cari tanpa batas unit; otorisasi unit dicek policy (403, bukan 404).
        return $kelas::query()->findOrFail($id);
    }

    private function label(Model $m): string
    {
        if ($m instanceof Pdil) {
            return $m->nama.($m->idpel ? " ({$m->idpel})" : '');
        }
        $pelanggan = $m->pelanggan ?? $m->invoice?->pelanggan;

        return $pelanggan ? "{$pelanggan->nama} ({$pelanggan->idpel})" : "#{$m->getKey()}";
    }
}
