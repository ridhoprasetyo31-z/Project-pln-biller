<?php

namespace App\Services;

use App\Enums\HasilTusbug;
use App\Enums\JenisAnomali;
use App\Enums\JenisLampiran;
use App\Enums\Role;
use App\Enums\StatusInvoice;
use App\Enums\StatusPelanggan;
use App\Enums\StatusTusbug;
use App\Models\Invoice;
use App\Models\Pelanggan;
use App\Models\Pengaturan;
use App\Models\Tusbug;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TugasTusbugBaru;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TUSBUG (pemutusan karena tunggakan): saran, penugasan, pelaksanaan, dan efek setelah disetujui.
 */
class TusbugService
{
    public function __construct(private readonly LampiranService $lampiran) {}

    /**
     * Pelanggan aktif di unit dengan tunggakan ≥ min_lembar_tusbug lembar
     * yang belum punya TUSBUG terbuka.
     */
    public function saran(?int $unitId): Collection
    {
        $minLembar = (int) Pengaturan::ambil('min_lembar_tusbug');

        $tunggakan = Invoice::query()
            ->where('status', StatusInvoice::Menunggak)
            ->when($unitId, fn ($q) => $q->diUnit($unitId))
            ->groupBy('pelanggan_id')
            ->havingRaw('COUNT(*) >= ?', [$minLembar])
            ->selectRaw('pelanggan_id, COUNT(*) as lembar, SUM(total) as total, MIN(jatuh_tempo) as tertua')
            ->get()
            ->keyBy('pelanggan_id');

        $sudahDitugaskan = Tusbug::query()->terbuka()->whereIn('pelanggan_id', $tunggakan->keys())->pluck('pelanggan_id');

        return Pelanggan::query()
            ->whereIn('id', $tunggakan->keys()->diff($sudahDitugaskan))
            ->where('status', StatusPelanggan::Aktif)
            ->with('rute:id,kode,biller_id', 'rute.biller:id,name')
            ->get()
            ->map(fn (Pelanggan $p) => [
                'pelanggan_id' => $p->id,
                'idpel' => $p->idpel,
                'nama' => $p->nama,
                'alamat' => $p->alamat,
                'rute' => $p->rute?->kode,
                'biller_id' => $p->rute?->biller_id,
                'biller' => $p->rute?->biller?->name,
                'lembar' => (int) $tunggakan[$p->id]->lembar,
                'total' => (float) $tunggakan[$p->id]->total,
                'tertua' => (string) $tunggakan[$p->id]->tertua,
            ])
            ->sortByDesc('lembar')
            ->values();
    }

    /**
     * Tugaskan TUSBUG untuk beberapa pelanggan. Biller = pilihan koordinator,
     * atau (bila kosong) biller pemegang rute masing-masing pelanggan.
     *
     * @param  list<int>  $pelangganIds
     * @return Collection<int, Tusbug>
     *
     * @throws ValidationException
     */
    public function tugaskan(User $koordinator, array $pelangganIds, ?int $billerId, string $tanggalRencana): Collection
    {
        $saran = $this->saran($koordinator->unit_id)->keyBy('pelanggan_id');
        $tidakValid = collect($pelangganIds)->reject(fn ($id) => $saran->has($id));
        if ($tidakValid->isNotEmpty()) {
            throw ValidationException::withMessages(['pelanggan_ids' => 'Sebagian pelanggan sudah tidak memenuhi syarat atau sudah ditugaskan. Muat ulang halaman.']);
        }

        $kodeUnit = Unit::whereKey($koordinator->unit_id)->value('kode') ?? 'UNIT';
        $tanggal = CarbonImmutable::parse($tanggalRencana);

        $hasil = DB::transaction(function () use ($koordinator, $pelangganIds, $billerId, $saran, $kodeUnit, $tanggal) {
            $urut = Tusbug::where('nomor_surat', 'like', "TSB/{$kodeUnit}/".now()->format('Ym').'/%')->count();

            return collect($pelangganIds)->map(function ($id) use ($koordinator, $billerId, $saran, $kodeUnit, $tanggal, &$urut) {
                $s = $saran[$id];
                $biller = $billerId ?? $s['biller_id'];
                if (! $biller || ! User::whereKey($biller)->where('role', Role::Biller)->where('unit_id', $koordinator->unit_id)->exists()) {
                    throw ValidationException::withMessages(['biller_id' => "Rute pelanggan {$s['nama']} belum punya biller. Pilih biller secara manual."]);
                }

                $tusbug = Tusbug::create([
                    'nomor_surat' => sprintf('TSB/%s/%s/%04d', $kodeUnit, now()->format('Ym'), ++$urut),
                    'pelanggan_id' => $id,
                    'biller_id' => $biller,
                    'ditugaskan_oleh' => $koordinator->id,
                    'jumlah_lembar' => $s['lembar'],
                    'total_tunggakan' => $s['total'],
                    'tanggal_rencana' => $tanggal->toDateString(),
                    'status' => StatusTusbug::Ditugaskan,
                ]);
                $tusbug->invoice()->attach(Invoice::where('pelanggan_id', $id)->where('status', StatusInvoice::Menunggak)->pluck('id'));

                return $tusbug;
            });
        });

        // Satu notifikasi per biller, berisi jumlah tugas barunya.
        $hasil->groupBy('biller_id')->each(function (Collection $tugas, int $billerId) use ($tanggal, $koordinator) {
            User::find($billerId)?->notify(new TugasTusbugBaru($tugas->count(), $tanggal, $koordinator->name));
        });

        return $hasil;
    }

    /**
     * Biller mengisi hasil pelaksanaan (baru atau perbaikan setelah revisi).
     *
     * @param  array{hasil: string, keterangan?: string|null, latitude?: float|null, longitude?: float|null}  $data
     * @param  array{sebelum?: UploadedFile|null, sesudah?: UploadedFile|null}  $foto
     *
     * @throws ValidationException
     */
    public function laksanakan(User $biller, Tusbug $tusbug, array $data, array $foto): Tusbug
    {
        if (! $tusbug->bisaDilaksanakan()) {
            throw ValidationException::withMessages(['tusbug' => 'TUSBUG ini sedang diverifikasi atau sudah selesai.']);
        }

        return DB::transaction(function () use ($biller, $tusbug, $data, $foto) {
            $tusbug->loadMissing('pelanggan');
            $lat = isset($data['latitude']) ? (float) $data['latitude'] : $tusbug->latitude;
            $lng = isset($data['longitude']) ? (float) $data['longitude'] : $tusbug->longitude;
            $jarak = GeoDistance::meter($lat, $lng, $tusbug->pelanggan->latitude, $tusbug->pelanggan->longitude);

            $tusbug->update([
                'hasil' => HasilTusbug::from($data['hasil']),
                'keterangan' => $data['keterangan'] ?? null,
                'dilaksanakan_at' => now(),
                'latitude' => $lat,
                'longitude' => $lng,
                'jarak_m' => $jarak !== null ? round($jarak, 1) : null,
            ]);

            $folder = 'tusbug/'.now()->format('Ym');
            foreach (['sebelum' => JenisLampiran::Sebelum, 'sesudah' => JenisLampiran::Sesudah] as $kunci => $jenis) {
                if (! empty($foto[$kunci])) {
                    $this->lampiran->ganti($tusbug, $foto[$kunci], $jenis, $biller, $folder, $lat, $lng, $data["foto_{$kunci}_diambil_at"] ?? null);
                }
            }

            $this->periksa($tusbug);

            return $tusbug;
        });
    }

    private function periksa(Tusbug $t): void
    {
        $anomali = [];
        $ambang = (float) Pengaturan::ambil('ambang_jarak_m');

        if ($t->jarak_m !== null && $t->jarak_m > $ambang) {
            $anomali[] = [
                'jenis' => JenisAnomali::LokasiJauh,
                'nilai' => $t->jarak_m,
                'keterangan' => sprintf('Dilaksanakan %s m dari titik pelanggan. Batas %s m.', number_format($t->jarak_m, 0, ',', '.'), number_format($ambang, 0, ',', '.')),
            ];
        }

        $jenisFoto = $t->lampiran()->pluck('jenis')->map(fn ($j) => $j->value)->all();
        $kurang = array_filter([
            ! in_array('sebelum', $jenisFoto, true) ? 'foto sebelum' : null,
            ! in_array('sesudah', $jenisFoto, true) ? 'foto sesudah' : null,
            $t->latitude === null ? 'lokasi GPS' : null,
        ]);
        if ($kurang) {
            $anomali[] = ['jenis' => JenisAnomali::BuktiTidakLengkap, 'nilai' => null, 'keterangan' => 'Tanpa '.implode(', ', $kurang).'.'];
        }

        $t->anomali()->delete();
        foreach ($anomali as $a) {
            $t->anomali()->create($a);
        }
        $t->update(['status' => $anomali ? StatusTusbug::Ditandai : StatusTusbug::MenungguVerifikasi]);
    }

    /**
     * Dipanggil saat TUSBUG disetujui:
     * diputus → pelanggan putus sementara; bayar di tempat → rekening lunas (pelanggan tetap/kembali aktif).
     */
    public function terapkan(Tusbug $tusbug): void
    {
        $tusbug->loadMissing('pelanggan', 'invoice');

        match ($tusbug->hasil) {
            HasilTusbug::Diputus => $tusbug->pelanggan->update(['status' => StatusPelanggan::PutusSementara]),
            HasilTusbug::BayarDiTempat => $tusbug->invoice
                ->filter(fn (Invoice $i) => $i->bisaDitagih())
                ->each(fn (Invoice $i) => $i->update(['status' => StatusInvoice::Lunas, 'dibayar_at' => $tusbug->dilaksanakan_at ?? now()])),
            default => null,
        };
    }
}
