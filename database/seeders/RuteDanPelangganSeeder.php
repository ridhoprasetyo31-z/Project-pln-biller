<?php

namespace Database\Seeders;

use App\Models\Rute;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 6 rute (3 per ULP), masing-masing 120 pelanggan berurutan dengan koordinat
 * yang menyusuri jalan di sekitar kelurahan. Data dibuat deterministik.
 */
class RuteDanPelangganSeeder extends Seeder
{
    private const PELANGGAN_PER_RUTE = 120;

    /** kode unit => daftar rute [kode, nama, kelurahan, email biller, lat, lng, jalan[]] */
    private const RUTE = [
        '18110' => [
            ['PNM-01', 'Tuah Karya 1', 'Tuah Karya', 'biller@biller.test', 0.4650, 101.3790, ['Jl. Garuda Sakti', 'Jl. Taman Karya', 'Gg. Bersama', 'Jl. Purwodadi']],
            ['PNM-02', 'Sidomulyo Barat 1', 'Sidomulyo Barat', 'dewi@biller.test', 0.4705, 101.4020, ['Jl. Soekarno Hatta', 'Jl. Delima', 'Jl. Kamboja', 'Gg. Melati']],
            ['PNM-03', 'Simpang Baru 1', 'Simpang Baru', 'rudi@biller.test', 0.4580, 101.3720, ['Jl. HR Soebrantas', 'Jl. Manyar Sakti', 'Jl. Bangau Sakti', 'Gg. Pinang']],
        ],
        '18120' => [
            ['RMB-01', 'Lembah Damai 1', 'Lembah Damai', 'fajar@biller.test', 0.6010, 101.4560, ['Jl. Sekolah', 'Jl. Lembah Damai', 'Gg. Wakaf', 'Jl. Pesantren']],
            ['RMB-02', 'Umban Sari 1', 'Umban Sari', 'yuni@biller.test', 0.5930, 101.4330, ['Jl. Umban Sari', 'Jl. Sembilang', 'Gg. Nuri', 'Jl. Muara Takus']],
            ['RMB-03', 'Meranti Pandak 1', 'Meranti Pandak', 'agus@biller.test', 0.5770, 101.4460, ['Jl. Yos Sudarso', 'Jl. Sail', 'Gg. Kenanga', 'Jl. Meranti']],
        ],
    ];

    private const NAMA_DEPAN = [
        'Ahmad', 'Siti', 'Budi', 'Nur', 'Rahmat', 'Dewi', 'Hasan', 'Fitri', 'Joko', 'Lina', 'Rizki', 'Wahyu',
        'Sri', 'Andi', 'Yanti', 'Hendra', 'Ratna', 'Irwan', 'Mega', 'Taufik', 'Rosmawati', 'Zulkifli', 'Eka', 'Syafri',
    ];

    private const NAMA_BELAKANG = [
        'Saputra', 'Lestari', 'Siregar', 'Nasution', 'Harahap', 'Hidayat', 'Rahayu', 'Lubis', 'Pratama', 'Wijaya',
        'Kurniawan', 'Putri', 'Syahputra', 'Hasibuan', 'Daulay', 'Fauzi', 'Ningsih', 'Sitompul', 'Rangkuti', 'Effendi',
    ];

    private const LETAK = ['Teras depan', 'Dinding samping kiri', 'Dinding samping kanan', 'Pagar depan', 'Garasi'];

    public function run(): void
    {
        mt_srand(2026);
        $sekarang = now();

        foreach (self::RUTE as $kodeUnit => $daftar) {
            $unit = Unit::where('kode', $kodeUnit)->firstOrFail();

            foreach ($daftar as $noRute => [$kode, $nama, $kelurahan, $emailBiller, $lat, $lng, $jalan]) {
                $rute = Rute::updateOrCreate(
                    ['unit_id' => $unit->id, 'kode' => $kode],
                    [
                        'nama' => $nama,
                        'kelurahan' => $kelurahan,
                        'biller_id' => User::where('email', $emailBiller)->value('id'),
                        'is_active' => true,
                    ],
                );

                if ($rute->pelanggan()->exists()) {
                    continue;
                }

                $rows = [];
                for ($i = 1; $i <= self::PELANGGAN_PER_RUTE; $i++) {
                    // 30 rumah per jalan, jalan bergantian arah utara-timur.
                    $ruas = intdiv($i - 1, 30);
                    $langkah = ($i - 1) % 30;
                    $offLat = $ruas % 2 === 0 ? $langkah * 0.00011 : 30 * 0.00011;
                    $offLng = $ruas * 0.0009 + ($ruas % 2 === 1 ? $langkah * 0.00011 : 0);

                    [$jenis, $golongan, $daya] = $this->profilTarif($i);

                    $rows[] = [
                        'idpel' => sprintf('%s%d%06d', $kodeUnit, $noRute + 1, $i),
                        'rute_id' => $rute->id,
                        'urutan_rute' => $i,
                        'nama' => $this->nama(),
                        'alamat' => sprintf('%s No. %d', $jalan[$ruas], $langkah * 2 + 1),
                        'kelurahan' => $kelurahan,
                        'jenis' => $jenis,
                        'golongan_tarif' => $golongan,
                        'daya_va' => $daya,
                        'nomor_meter' => sprintf('45%d%d%07d', substr($kodeUnit, 3, 1), $noRute + 1, $i * 37 + 1000),
                        'letak_meter' => self::LETAK[mt_rand(0, count(self::LETAK) - 1)],
                        'latitude' => round($lat + $offLat + mt_rand(-15, 15) / 1_000_000, 7),
                        'longitude' => round($lng + $offLng + mt_rand(-15, 15) / 1_000_000, 7),
                        'status' => $i % 60 === 0 ? 'putus_sementara' : 'aktif',
                        'created_at' => $sekarang,
                        'updated_at' => $sekarang,
                    ];
                }

                DB::table('pelanggan')->insert($rows);
            }
        }
    }

    /** @return array{0: string, 1: string, 2: int} jenis, golongan, daya */
    private function profilTarif(int $i): array
    {
        if ($i % 25 === 0) {
            return ['pascabayar', 'S2', [900, 1300][mt_rand(0, 1)]];
        }
        if ($i % 12 === 0) {
            return ['pascabayar', 'B1', [2200, 3500, 5500][mt_rand(0, 2)]];
        }

        $jenis = $i % 5 === 0 ? 'prabayar' : 'pascabayar';
        $daya = [900, 900, 1300, 1300, 1300, 2200, 2200, 3500][mt_rand(0, 7)];

        return [$jenis, 'R1', $daya];
    }

    private function nama(): string
    {
        return self::NAMA_DEPAN[mt_rand(0, count(self::NAMA_DEPAN) - 1)].' '
            .self::NAMA_BELAKANG[mt_rand(0, count(self::NAMA_BELAKANG) - 1)];
    }
}
