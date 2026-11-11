<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Pelanggan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\User;
use App\Services\GeoDistance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat catatan meter periode 202604–202609 yang sudah diverifikasi.
 * Dipakai untuk stand lalu, rata-rata 3 bulan, grafik pemakaian, dan skor kinerja.
 *
 * Setiap biller punya profil berbeda agar peringkat di dashboard bervariasi:
 * [persen terlewat, persen pernah direvisi, persen terlambat, persen lokasi jauh].
 */
class RiwayatPencatatanSeeder extends Seeder
{
    public const PROFIL = [
        'biller@biller.test' => [1, 3, 2, 2],
        'dewi@biller.test' => [0, 1, 1, 1],
        'rudi@biller.test' => [6, 12, 10, 8],
        'fajar@biller.test' => [2, 4, 5, 3],
        'yuni@biller.test' => [1, 2, 2, 2],
        'agus@biller.test' => [4, 8, 15, 6],
    ];

    public function run(): void
    {
        if (DB::table('pencatatan_meter')->exists()) {
            return;
        }

        mt_srand(20260401);
        FotoDemo::pastikanAda();

        $periode = Periode::whereIn('kode', PeriodeSeeder::DITUTUP)->orderBy('kode')->get();
        $profil = User::whereIn('email', array_keys(self::PROFIL))->get()->mapWithKeys(fn ($u) => [$u->id => self::PROFIL[$u->email]]);
        $koordinator = User::where('role', Role::KoordinatorAdmin)->pluck('id', 'unit_id');
        $unitRute = DB::table('rute')->pluck('unit_id', 'id');
        $pelanggan = Pelanggan::query()->aktif()->get(['id', 'rute_id', 'jenis', 'golongan_tarif', 'daya_va', 'latitude', 'longitude']);

        // Stand meter sebenarnya dan stand terakhir yang tercatat, mulai akhir Maret 2026.
        $stand = $pelanggan->mapWithKeys(fn ($p) => [$p->id => (float) mt_rand(3000, 40000)])->all();
        $tercatat = $stand;

        foreach ($periode as $per) {
            $penugasan = Penugasan::where('periode_id', $per->id)->get()->keyBy('rute_id');
            $mulai = CarbonImmutable::parse($per->tanggal_mulai)->setTime(8, 0);
            $batas = CarbonImmutable::parse($per->tanggal_selesai)->setTime(16, 0);
            $rows = [];
            $meta = [];

            foreach ($pelanggan as $p) {
                $tugas = $penugasan->get($p->rute_id);
                if (! $tugas) {
                    continue;
                }
                [$lewat, $revisi, $telat, $jauh] = $profil[$tugas->biller_id] ?? [1, 2, 2, 2];

                // Meter terus berputar; bila pelanggan terlewat, catatan berikutnya
                // menghitung pemakaian dari stand terakhir yang tercatat.
                $stand[$p->id] = round($stand[$p->id] + $this->pemakaianDasar($p) * mt_rand(85, 115) / 100, 1);
                if (mt_rand(1, 100) <= $lewat) {
                    continue;
                }
                $standLalu = $tercatat[$p->id];
                $pemakaian = round($stand[$p->id] - $standLalu, 1);
                $tercatat[$p->id] = $stand[$p->id];

                $isTelat = mt_rand(1, 100) <= $telat;
                $isJauh = mt_rand(1, 100) <= $jauh;
                $dikirim = $isTelat
                    ? $batas->addDays(mt_rand(1, 4))->addMinutes(mt_rand(0, 300))
                    : $mulai->addMinutes(mt_rand(0, (int) $mulai->diffInMinutes($batas)));
                $offsetM = $isJauh ? mt_rand(130, 320) : mt_rand(3, 45);
                $sudut = deg2rad(mt_rand(0, 359));
                $lat = round($p->latitude + $offsetM * cos($sudut) / 111_320, 7);
                $lng = round($p->longitude + $offsetM * sin($sudut) / 111_320, 7);
                $pasca = $p->jenis->value === 'pascabayar';

                $rows[] = [
                    'periode_id' => $per->id,
                    'pelanggan_id' => $p->id,
                    'biller_id' => $tugas->biller_id,
                    'penugasan_id' => $tugas->id,
                    'jenis' => $p->jenis->value,
                    'stand_lalu' => $pasca ? $standLalu : null,
                    'stand_kini' => $pasca ? $stand[$p->id] : null,
                    'pemakaian_kwh' => $pasca ? $pemakaian : null,
                    'sisa_kwh' => $pasca ? null : mt_rand(500, 9000) / 100,
                    'kondisi_meter' => 'normal',
                    'keterangan' => null,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'jarak_m' => round(GeoDistance::meter($lat, $lng, $p->latitude, $p->longitude), 1),
                    'dikirim_at' => $dikirim,
                    'tepat_waktu' => ! $isTelat,
                    'status' => 'disetujui',
                    'jumlah_revisi' => mt_rand(1, 100) <= $revisi ? 1 : 0,
                    'disetujui_at' => $dikirim->addHours(mt_rand(3, 30)),
                    'created_at' => $dikirim,
                    'updated_at' => $dikirim,
                ];
                $meta[$p->id] = ['telat' => $isTelat, 'jauh' => $isJauh, 'batas' => $batas, 'unit' => $unitRute[$p->rute_id]];
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('pencatatan_meter')->insert($chunk);
            }

            $this->lengkapi($per->id, $meta, $koordinator->all());
        }
    }

    /** Tambah foto, riwayat verifikasi, dan anomali untuk catatan yang baru disisipkan. */
    private function lengkapi(int $periodeId, array $meta, array $koordinator): void
    {
        $catatan = DB::table('pencatatan_meter')
            ->where('periode_id', $periodeId)
            ->get(['id', 'pelanggan_id', 'biller_id', 'jenis', 'latitude', 'longitude', 'dikirim_at', 'disetujui_at', 'jumlah_revisi', 'jarak_m']);

        $lampiran = $verifikasi = $anomali = [];

        foreach ($catatan as $c) {
            $m = $meta[$c->pelanggan_id];
            $foto = $c->jenis === 'pascabayar'
                ? FotoDemo::METER[$c->id % count(FotoDemo::METER)]
                : FotoDemo::LAYAR[$c->id % count(FotoDemo::LAYAR)];

            $lampiran[] = [
                'lampiranable_type' => 'pencatatan_meter',
                'lampiranable_id' => $c->id,
                'jenis' => $c->jenis === 'pascabayar' ? 'meter' : 'layar_meter',
                'path' => $foto,
                'thumbnail_path' => $foto,
                'ukuran_kb' => 64,
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
                'diambil_at' => $c->dikirim_at,
                'diunggah_oleh' => $c->biller_id,
                'created_at' => $c->dikirim_at,
                'updated_at' => $c->dikirim_at,
            ];

            $verifikator = $koordinator[$m['unit']] ?? null;
            if ($verifikator) {
                if ($c->jumlah_revisi > 0) {
                    $verifikasi[] = [
                        'verifiable_type' => 'pencatatan_meter',
                        'verifiable_id' => $c->id,
                        'verifikator_id' => $verifikator,
                        'keputusan' => 'ditolak',
                        'alasan' => ['angka_tidak_sesuai', 'foto_buram'][$c->id % 2],
                        'catatan' => null,
                        'created_at' => $c->dikirim_at,
                        'updated_at' => $c->dikirim_at,
                    ];
                }
                $verifikasi[] = [
                    'verifiable_type' => 'pencatatan_meter',
                    'verifiable_id' => $c->id,
                    'verifikator_id' => $verifikator,
                    'keputusan' => 'disetujui',
                    'alasan' => null,
                    'catatan' => null,
                    'created_at' => $c->disetujui_at,
                    'updated_at' => $c->disetujui_at,
                ];
            }

            if ($m['telat']) {
                $anomali[] = $this->anomali($c, 'lewat_jadwal', null, 'Dikirim setelah batas pencatatan.');
            }
            if ($m['jauh']) {
                $anomali[] = $this->anomali($c, 'lokasi_jauh', $c->jarak_m, sprintf('Dicatat %s m dari titik pelanggan.', number_format((float) $c->jarak_m, 0, ',', '.')));
            }
        }

        foreach ([['lampiran', $lampiran], ['verifikasi', $verifikasi], ['anomali', $anomali]] as [$tabel, $data]) {
            foreach (array_chunk($data, 1000) as $chunk) {
                DB::table($tabel)->insert($chunk);
            }
        }
    }

    private function anomali(object $c, string $jenis, ?float $nilai, string $keterangan): array
    {
        return [
            'anomaliable_type' => 'pencatatan_meter',
            'anomaliable_id' => $c->id,
            'jenis' => $jenis,
            'nilai' => $nilai,
            'keterangan' => $keterangan,
            'created_at' => $c->dikirim_at,
            'updated_at' => $c->dikirim_at,
        ];
    }

    /** Perkiraan pemakaian bulanan (kWh) dari daya dan golongan. */
    public static function pemakaianDasarStatic(string $golongan, int $daya): float
    {
        $dasar = match (true) {
            $daya <= 900 => 95,
            $daya <= 1300 => 150,
            $daya <= 2200 => 240,
            $daya <= 3500 => 380,
            default => 520,
        };

        return $dasar * match ($golongan) {
            'B1' => 1.3,
            'S2' => 0.9,
            default => 1.0,
        };
    }

    private function pemakaianDasar(Pelanggan $p): float
    {
        return self::pemakaianDasarStatic($p->golongan_tarif, $p->daya_va);
    }
}
