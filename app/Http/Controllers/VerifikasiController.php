<?php

namespace App\Http\Controllers;

use App\Enums\AlasanTolak;
use App\Enums\StatusCatatan;
use App\Http\Requests\TolakCatatanRequest;
use App\Models\PencatatanMeter;
use App\Models\Verifikasi;
use App\Services\RiwayatPemakaian;
use App\Services\VerificationService;
use App\Support\StatusCatatanView;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Antrean verifikasi koordinator. Tahap 3 berisi catatan meter; modul lain
 * (kunjungan penagihan, koreksi, PDIL, TUSBUG) bergabung di tahap berikutnya
 * lewat parameter {tipe} yang sama.
 */
class VerifikasiController extends Controller
{
    /** Tipe catatan yang bisa diverifikasi (alias morph map → model). */
    private const TIPE = [
        'pencatatan_meter' => PencatatanMeter::class,
    ];

    public function __construct(private readonly VerificationService $service) {}

    public function index(Request $request, RiwayatPemakaian $riwayat): Response
    {
        $unitId = $request->user()->unit_id;
        $dasar = fn () => PencatatanMeter::query()->diUnit($unitId);

        $jumlah = [
            'ditandai' => $dasar()->periodeTerbuka()->where('status', StatusCatatan::Ditandai)->count(),
            'normal' => $dasar()->periodeTerbuka()->where('status', StatusCatatan::MenungguVerifikasi)->count(),
            'diperiksa' => $dasar()->periodeTerbuka()->whereIn('status', [StatusCatatan::Disetujui, StatusCatatan::Revisi])->whereHas('verifikasi')->count(),
        ];

        $tab = $request->input('tab');
        if (! in_array($tab, ['ditandai', 'normal', 'diperiksa'], true)) {
            $tab = $jumlah['ditandai'] > 0 ? 'ditandai' : 'normal';
        }

        $antrean = $this->queryTab($dasar(), $tab)
            ->with([
                'pelanggan:id,idpel,nama',
                'biller:id,name',
                'anomali:id,anomaliable_id,anomaliable_type,jenis',
                'lampiran',
            ])
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PencatatanMeter $c) => [
                'id' => $c->id,
                'idpel' => $c->pelanggan->idpel,
                'nama' => $c->pelanggan->nama,
                'biller' => $c->biller->name,
                'dikirim_at' => $c->dikirim_at?->toIso8601String(),
                'status' => $c->status->value,
                'anomali' => $c->anomali->map(fn ($a) => $a->jenis->value)->unique()->values(),
                'thumb' => StatusCatatanView::fotoUtama($c)?->thumbnailUrl(),
                'jumlah_revisi' => $c->jumlah_revisi,
            ]);

        $dipilihId = $request->integer('id') ?: ($antrean->first()['id'] ?? null);
        $dipilih = $dipilihId
            ? $dasar()->with([
                'pelanggan', 'periode', 'biller:id,name,nip', 'anomali', 'lampiran',
                'verifikasi.verifikator:id,name',
            ])->find($dipilihId)
            : null;

        return Inertia::render('Verifikasi/Index', [
            'tab' => $tab,
            'jumlah' => $jumlah,
            'antrean' => $antrean,
            'detail' => $dipilih ? $this->detail($dipilih, $riwayat) : null,
            'alasanOptions' => AlasanTolak::options(),
        ]);
    }

    public function setujui(Request $request, string $tipe, int $id): RedirectResponse
    {
        $catatan = $this->temukan($tipe, $id);
        $this->authorize('verifikasi', $catatan);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:500'], 'tab' => ['nullable', 'string']]);
        $this->service->setujui($catatan, $request->user(), $data['catatan'] ?? null);

        return to_route('verifikasi.index', ['tab' => $data['tab'] ?? null])
            ->with('success', "Catatan {$this->label($catatan)} disetujui.");
    }

    public function tolak(TolakCatatanRequest $request, string $tipe, int $id): RedirectResponse
    {
        $catatan = $this->temukan($tipe, $id);
        $this->authorize('verifikasi', $catatan);

        $this->service->tolak(
            $catatan,
            $request->user(),
            AlasanTolak::from($request->validated('alasan')),
            $request->validated('catatan'),
        );

        return to_route('verifikasi.index', ['tab' => $request->validated('tab')])
            ->with('success', "Catatan {$this->label($catatan)} dikembalikan ke biller untuk revisi.");
    }

    private function queryTab(Builder $q, string $tab): Builder
    {
        return match ($tab) {
            'ditandai' => $q->periodeTerbuka()->where('status', StatusCatatan::Ditandai)->orderBy('dikirim_at'),
            'normal' => $q->periodeTerbuka()->where('status', StatusCatatan::MenungguVerifikasi)->orderBy('dikirim_at'),
            // Hanya periode terbuka; catatan periode yang sudah ditutup ada di Laporan.
            default => $q->periodeTerbuka()->whereIn('status', [StatusCatatan::Disetujui, StatusCatatan::Revisi])
                ->whereHas('verifikasi')
                ->orderByDesc(Verifikasi::query()
                    ->select('id')
                    ->whereColumn('verifiable_id', 'pencatatan_meter.id')
                    ->where('verifiable_type', 'pencatatan_meter')
                    ->latest('id')
                    ->limit(1)),
        };
    }

    private function detail(PencatatanMeter $c, RiwayatPemakaian $riwayat): array
    {
        $pelanggan = $c->pelanggan;
        $pasca = $c->jenis->value === 'pascabayar';
        $histori = $riwayat->untuk($pelanggan->id, $c->periode, 3)->reverse()->values();
        $bulan = fn (string $kode) => CarbonImmutable::createFromFormat('Ym', $kode)->startOfMonth()->locale('id')->translatedFormat('M');
        $foto = StatusCatatanView::fotoUtama($c);

        $bars = $histori->map(fn ($h) => [
            'label' => $bulan($h->kode),
            'nilai' => $pasca ? $h->pemakaian_kwh : $h->sisa_kwh,
            'kini' => false,
        ])->push([
            'label' => $bulan($c->periode->kode),
            'nilai' => $pasca ? $c->pemakaian_kwh : $c->sisa_kwh,
            'kini' => true,
        ]);

        return [
            'id' => $c->id,
            'tipe' => 'pencatatan_meter',
            'status' => $c->status->value,
            'jenis' => $c->jenis->value,
            'periode' => $c->periode->kode,
            'pelanggan' => [
                'idpel' => $pelanggan->idpel,
                'nama' => $pelanggan->nama,
                'alamat' => "{$pelanggan->alamat}, {$pelanggan->kelurahan}",
                'tarif' => $pelanggan->labelTarif(),
                'nomor_meter' => $pelanggan->nomor_meter,
                'latitude' => $pelanggan->latitude,
                'longitude' => $pelanggan->longitude,
            ],
            'biller' => $c->biller->only(['name', 'nip']),
            'stand_lalu' => $c->stand_lalu,
            'stand_kini' => $c->stand_kini,
            'pemakaian_kwh' => $c->pemakaian_kwh,
            'sisa_kwh' => $c->sisa_kwh,
            'rata_tiga_bulan' => $pasca ? $riwayat->rataTigaBulan($pelanggan->id, $c->periode) : null,
            'kondisi' => $c->kondisi_meter->label(),
            'keterangan' => $c->keterangan,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'jarak_m' => $c->jarak_m,
            'dikirim_at' => $c->dikirim_at?->toIso8601String(),
            'tepat_waktu' => $c->tepat_waktu,
            'jumlah_revisi' => $c->jumlah_revisi,
            'foto' => StatusCatatanView::foto($foto),
            'anomali' => StatusCatatanView::anomali($c->anomali),
            'riwayatVerifikasi' => $c->verifikasi->map(fn ($v) => StatusCatatanView::verifikasi($v))->values(),
            'bars' => $bars,
            'bisaDiputuskan' => in_array($c->status, [StatusCatatan::MenungguVerifikasi, StatusCatatan::Ditandai], true)
                && $c->periode->bisaDiubah(),
        ];
    }

    private function temukan(string $tipe, int $id): PencatatanMeter
    {
        abort_unless(isset(self::TIPE[$tipe]), 404);

        return self::TIPE[$tipe]::with('pelanggan:id,idpel,nama')->findOrFail($id);
    }

    private function label(PencatatanMeter $c): string
    {
        return "{$c->pelanggan->nama} ({$c->pelanggan->idpel})";
    }
}
