<?php

namespace App\Http\Controllers;

use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Enums\StatusCatatan;
use App\Http\Requests\CatatMeterRequest;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Pengaturan;
use App\Models\Penugasan;
use App\Policies\PencatatanMeterPolicy;
use App\Services\PencatatanService;
use App\Services\RiwayatPemakaian;
use App\Support\StatusCatatanView;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatatMeterController extends Controller
{
    public function show(Request $request, Pelanggan $pelanggan, string $jenis, RiwayatPemakaian $riwayat): Response|RedirectResponse
    {
        $this->authorize('catat', [PencatatanMeter::class, $pelanggan]);

        // URL jenis keliru → arahkan ke form yang sesuai jenis pelanggan.
        if ($pelanggan->jenis->value !== $jenis) {
            return to_route("catat.{$pelanggan->jenis->value}", $pelanggan);
        }

        $penugasan = PencatatanMeterPolicy::penugasanUntuk($request->user(), $pelanggan)->load('periode', 'rute:id,kode,nama');
        $periode = $penugasan->periode;
        $pasca = $pelanggan->jenis === JenisPelanggan::Pascabayar;

        $catatan = PencatatanMeter::query()
            ->with(['anomali', 'lampiran', 'verifikasiTerakhir.verifikator:id,name'])
            ->where('periode_id', $periode->id)
            ->where('pelanggan_id', $pelanggan->id)
            ->first();

        $histori = $riwayat->untuk($pelanggan->id, $periode, 6)->reverse()->values();

        return Inertia::render($pasca ? 'CatatMeter/Pascabayar' : 'CatatMeter/Prabayar', [
            'periode' => [
                'kode' => $periode->kode,
                'nama' => $periode->nama(),
                'tanggal_selesai' => $periode->tanggal_selesai->toDateString(),
            ],
            'pelanggan' => [
                'id' => $pelanggan->id,
                'idpel' => $pelanggan->idpel,
                'nama' => $pelanggan->nama,
                'alamat' => $pelanggan->alamat,
                'kelurahan' => $pelanggan->kelurahan,
                'tarif' => $pelanggan->labelTarif(),
                'nomor_meter' => $pelanggan->nomor_meter,
                'letak_meter' => $pelanggan->letak_meter,
                'latitude' => $pelanggan->latitude,
                'longitude' => $pelanggan->longitude,
                'urutan' => $pelanggan->urutan_rute,
                'rute' => $penugasan->rute->kode,
            ],
            'standLalu' => $pasca ? ($histori->last()?->stand_kini) : null,
            'rataTigaBulan' => $pasca ? $riwayat->rataTigaBulan($pelanggan->id, $periode) : null,
            'riwayat' => $histori->map(fn ($h) => [
                'kode' => $h->kode,
                'label' => CarbonImmutable::createFromFormat('Ym', $h->kode)->startOfMonth()->locale('id')->translatedFormat('M'),
                'nilai' => $pasca ? $h->pemakaian_kwh : $h->sisa_kwh,
            ]),
            'catatan' => $catatan ? [
                'status' => $catatan->status->value,
                'stand_kini' => $catatan->stand_kini,
                'sisa_kwh' => $catatan->sisa_kwh,
                'kondisi_meter' => $catatan->kondisi_meter->value,
                'keterangan' => $catatan->keterangan,
                'latitude' => $catatan->latitude,
                'longitude' => $catatan->longitude,
                'jarak_m' => $catatan->jarak_m,
                'dikirim_at' => $catatan->dikirim_at?->toIso8601String(),
                'jumlah_revisi' => $catatan->jumlah_revisi,
                'foto' => StatusCatatanView::foto(StatusCatatanView::fotoUtama($catatan)),
                'anomali' => StatusCatatanView::anomali($catatan->anomali),
                'verifikasi' => StatusCatatanView::verifikasi($catatan->verifikasiTerakhir),
            ] : null,
            'bisaDiubah' => ! $catatan || $catatan->bisaDiubahBiller(),
            'navigasi' => $this->navigasi($pelanggan),
            'progres' => [
                'terkirim' => PencatatanMeter::where('penugasan_id', $penugasan->id)->where('status', '!=', StatusCatatan::Draft)->count(),
                'target' => $penugasan->target_pelanggan,
            ],
            'kondisiOptions' => KondisiMeter::options($pasca ? KondisiMeter::untukPascabayar() : KondisiMeter::untukPrabayar()),
            'ambang' => [
                'lonjakan_persen' => (float) Pengaturan::ambil('ambang_lonjakan_persen'),
                'jarak_m' => (float) Pengaturan::ambil('ambang_jarak_m'),
            ],
        ]);
    }

    public function store(CatatMeterRequest $request, Pelanggan $pelanggan, PencatatanService $service): RedirectResponse
    {
        $penugasan = PencatatanMeterPolicy::penugasanUntuk($request->user(), $pelanggan);
        $kirim = $request->kirim();

        $catatan = $service->simpan(
            $request->user(),
            $pelanggan,
            $penugasan,
            $request->safe()->except(['aksi', 'foto']),
            $request->file('foto'),
            $kirim,
        );

        if (! $kirim) {
            return back()->with('success', "Draf {$pelanggan->nama} disimpan.");
        }

        $pesan = $catatan->status === StatusCatatan::Ditandai
            ? "Catatan {$pelanggan->nama} terkirim dan ditandai sistem untuk diperiksa koordinator."
            : "Catatan {$pelanggan->nama} terkirim.";

        $berikutnya = $this->belumDicatatBerikutnya($penugasan, $pelanggan);

        return $berikutnya
            ? to_route("catat.{$berikutnya->jenis->value}", $berikutnya)->with('success', $pesan)
            : to_route('tugas.index')->with('success', $pesan.' Semua pelanggan di rute ini sudah dicatat.');
    }

    /** Pelanggan sebelum/sesudah di urutan rute. */
    private function navigasi(Pelanggan $pelanggan): array
    {
        $ringkas = fn (?Pelanggan $p) => $p ? [
            'urutan' => $p->urutan_rute,
            'nama' => $p->nama,
            'url' => route("catat.{$p->jenis->value}", $p),
        ] : null;

        $dasar = Pelanggan::query()->aktif()->where('rute_id', $pelanggan->rute_id);

        return [
            'sebelumnya' => $ringkas((clone $dasar)->where('urutan_rute', '<', $pelanggan->urutan_rute)->orderByDesc('urutan_rute')->first()),
            'berikutnya' => $ringkas((clone $dasar)->where('urutan_rute', '>', $pelanggan->urutan_rute)->orderBy('urutan_rute')->first()),
        ];
    }

    /** Pelanggan berikutnya (sesuai urutan, memutar ke awal) yang belum dikirim atau perlu revisi. */
    private function belumDicatatBerikutnya(Penugasan $penugasan, Pelanggan $sekarang): ?Pelanggan
    {
        $sudah = PencatatanMeter::query()
            ->where('periode_id', $penugasan->periode_id)
            ->whereNotIn('status', [StatusCatatan::Draft->value, StatusCatatan::Revisi->value])
            ->select('pelanggan_id');

        $belum = Pelanggan::query()
            ->aktif()
            ->where('rute_id', $penugasan->rute_id)
            ->whereKeyNot($sekarang->id)
            ->whereNotIn('id', $sudah);

        return (clone $belum)->where('urutan_rute', '>', $sekarang->urutan_rute)->orderBy('urutan_rute')->first()
            ?? $belum->orderBy('urutan_rute')->first();
    }
}
