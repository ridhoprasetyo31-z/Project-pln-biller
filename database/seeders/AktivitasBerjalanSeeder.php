<?php

namespace Database\Seeders;

use App\Enums\AlasanTolak;
use App\Enums\JenisLampiran;
use App\Enums\JenisPelanggan;
use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\User;
use App\Services\GeoDistance;
use App\Services\PencatatanService;
use App\Services\RiwayatPemakaian;
use App\Services\VerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Aktivitas periode berjalan (202610) lewat service sungguhan, agar status,
 * anomali, dan antrean verifikasi konsisten dengan aturan aplikasi:
 * sebagian sudah disetujui, sebagian menunggu, beberapa ditandai sistem,
 * dan dua catatan Andi dikembalikan untuk revisi.
 */
class AktivitasBerjalanSeeder extends Seeder
{
    /** Jumlah pelanggan yang sudah dikirim per biller (target 118–120). */
    private const TERKIRIM = [
        'biller@biller.test' => 34,
        'dewi@biller.test' => 88,
        'rudi@biller.test' => 52,
        'fajar@biller.test' => 70,
        'yuni@biller.test' => 81,
        'agus@biller.test' => 45,
    ];

    public function run(PencatatanService $pencatatan, VerificationService $verifikasi, RiwayatPemakaian $riwayat): void
    {
        $periode = Periode::where('kode', PeriodeSeeder::BERJALAN)->first();
        if (! $periode || PencatatanMeter::where('periode_id', $periode->id)->exists()) {
            return;
        }

        mt_srand(20261026);
        FotoDemo::pastikanAda();

        $mulai = CarbonImmutable::parse($periode->tanggal_mulai)->setTime(8, 0);
        $akhir = CarbonImmutable::now()->subHour()->min(CarbonImmutable::parse($periode->tanggal_selesai)->setTime(16, 0));
        $koordinator = User::where('role', Role::KoordinatorAdmin)->get()->keyBy('unit_id');

        $penugasan = Penugasan::with(['biller', 'rute'])->where('periode_id', $periode->id)->get();

        foreach ($penugasan as $tugas) {
            $jumlah = self::TERKIRIM[$tugas->biller->email] ?? 40;
            $verifikator = $koordinator->get($tugas->rute->unit_id);
            $andi = $tugas->biller->email === 'biller@biller.test';

            $pelanggan = Pelanggan::query()->aktif()->where('rute_id', $tugas->rute_id)
                ->orderBy('urutan_rute')->limit($jumlah)->get();

            foreach ($pelanggan->values() as $i => $p) {
                $no = $i + 1;
                // Waktu kirim tersebar merata dari awal periode sampai sekarang.
                $dikirim = $mulai->addSeconds((int) (($akhir->getTimestamp() - $mulai->getTimestamp()) * $no / ($jumlah + 1)));

                $skenario = match (true) {
                    $andi && in_array($no, [5, 27], true) => 'lonjakan',
                    $andi && $no === 9 => 'stand_turun',
                    $andi && in_array($no, [12, 20], true) => 'revisi',
                    ! $andi && $no % 17 === 0 => 'lonjakan',
                    ! $andi && $no % 29 === 0 => 'stand_turun',
                    ! $andi && $no % 23 === 0 => 'jauh',
                    default => 'normal',
                };

                $catatan = $this->buat($p, $tugas, $periode, $skenario, $dikirim, $riwayat);
                $pencatatan->kirim($catatan, $p, $dikirim);
                $catatan->refresh();

                if (! $verifikator) {
                    continue;
                }

                if ($skenario === 'revisi') {
                    $verifikasi->tolak($catatan, $verifikator, $no === 12 ? AlasanTolak::AngkaTidakSesuai : AlasanTolak::FotoBuram,
                        $no === 12 ? 'Angka di foto terbaca 4 digit terakhir berbeda, mohon cek ulang.' : 'Foto tidak fokus, angka meter tidak terbaca.');
                } elseif ($catatan->status === StatusCatatan::MenungguVerifikasi && $no <= (int) floor($jumlah * 0.4)) {
                    // Catatan lama yang wajar sudah disetujui koordinator.
                    $verifikasi->setujui($catatan, $verifikator);
                }
            }
        }
    }

    private function buat(Pelanggan $p, Penugasan $tugas, Periode $periode, string $skenario, CarbonImmutable $dikirim, RiwayatPemakaian $riwayat): PencatatanMeter
    {
        $pasca = $p->jenis === JenisPelanggan::Pascabayar;
        $standLalu = $pasca ? $riwayat->standLalu($p->id, $periode) : null;
        $rata = $pasca ? ($riwayat->rataTigaBulan($p->id, $periode) ?? RiwayatPencatatanSeeder::pemakaianDasarStatic($p->golongan_tarif, $p->daya_va)) : null;

        $pemakaian = match ($skenario) {
            'lonjakan' => round($rata * mt_rand(220, 320) / 100, 1),
            'stand_turun' => -round(mt_rand(20, 90), 1),
            default => $rata !== null ? round($rata * mt_rand(88, 112) / 100, 1) : null,
        };
        $standKini = ($pasca && $standLalu !== null) ? round($standLalu + $pemakaian, 1) : null;

        $offsetM = $skenario === 'jauh' ? mt_rand(140, 260) : mt_rand(3, 40);
        $sudut = deg2rad(mt_rand(0, 359));
        $lat = round($p->latitude + $offsetM * cos($sudut) / 111_320, 7);
        $lng = round($p->longitude + $offsetM * sin($sudut) / 111_320, 7);

        $catatan = PencatatanMeter::create([
            'periode_id' => $periode->id,
            'pelanggan_id' => $p->id,
            'biller_id' => $tugas->biller_id,
            'penugasan_id' => $tugas->id,
            'jenis' => $p->jenis,
            'stand_lalu' => $standLalu,
            'stand_kini' => $standKini,
            'pemakaian_kwh' => ($standKini !== null) ? round($standKini - $standLalu, 1) : null,
            'sisa_kwh' => $pasca ? null : mt_rand(500, 9000) / 100,
            'kondisi_meter' => 'normal',
            'latitude' => $lat,
            'longitude' => $lng,
            'jarak_m' => round(GeoDistance::meter($lat, $lng, $p->latitude, $p->longitude), 1),
            'status' => StatusCatatan::Draft,
            'created_at' => $dikirim,
        ]);

        $foto = $pasca ? FotoDemo::METER[$p->id % count(FotoDemo::METER)] : FotoDemo::LAYAR[$p->id % count(FotoDemo::LAYAR)];
        $catatan->lampiran()->create([
            'jenis' => $pasca ? JenisLampiran::Meter : JenisLampiran::LayarMeter,
            'path' => $foto,
            'thumbnail_path' => $foto,
            'ukuran_kb' => 64,
            'latitude' => $lat,
            'longitude' => $lng,
            'diambil_at' => $dikirim,
            'diunggah_oleh' => $tugas->biller_id,
        ]);

        return $catatan;
    }
}
