<?php

use App\Enums\JenisAnomali;
use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Services\AnomalyDetector;
use App\Services\DataPemeriksaan;
use Carbon\CarbonImmutable;

function periksa(array $ubah = []): array
{
    $dasar = [
        'jenis' => JenisPelanggan::Pascabayar,
        'kondisi' => KondisiMeter::Normal,
        'standLalu' => 1000.0,
        'standKini' => 1150.0,
        'rataTigaBulan' => 150.0,
        'latitude' => 0.465,
        'longitude' => 101.379,
        'jarakM' => 20.0,
        'adaFoto' => true,
        'dikirimAt' => CarbonImmutable::parse('2026-11-10 10:00'),
        'batasPencatatan' => CarbonImmutable::parse('2026-11-25'),
    ];

    $detector = new AnomalyDetector(['ambang_lonjakan_persen' => 150, 'ambang_jarak_m' => 100]);

    return array_map(
        fn ($a) => $a['jenis'],
        $detector->periksa(new DataPemeriksaan(...array_merge($dasar, $ubah))),
    );
}

test('catatan wajar tidak menghasilkan anomali', function () {
    expect(periksa())->toBe([]);
});

test('stand turun dengan kondisi normal ditandai', function () {
    expect(periksa(['standKini' => 990.0]))->toContain(JenisAnomali::StandTurun);
});

test('stand turun tidak ditandai bila kondisi meter rusak', function () {
    expect(periksa(['standKini' => 990.0, 'kondisi' => KondisiMeter::MeterRusak]))->not->toContain(JenisAnomali::StandTurun);
});

test('lonjakan bila pemakaian lebih dari 150 persen rata-rata 3 bulan', function () {
    expect(periksa(['standKini' => 1225.0]))->not->toContain(JenisAnomali::Lonjakan) // 225 kWh = 150%, belum lewat
        ->and(periksa(['standKini' => 1226.0]))->toContain(JenisAnomali::Lonjakan); // 226 kWh > 150%
});

test('lonjakan tidak dihitung tanpa riwayat', function () {
    expect(periksa(['standKini' => 5000.0, 'rataTigaBulan' => null]))->not->toContain(JenisAnomali::Lonjakan);
});

test('pemakaian nol dengan kondisi normal ditandai, kecuali rumah kosong', function () {
    expect(periksa(['standKini' => 1000.0]))->toContain(JenisAnomali::PemakaianNol)
        ->and(periksa(['standKini' => 1000.0, 'kondisi' => KondisiMeter::RumahKosong]))->toBe([]);
});

test('lokasi lebih dari 100 m dari pelanggan ditandai', function () {
    expect(periksa(['jarakM' => 100.0]))->toBe([])
        ->and(periksa(['jarakM' => 100.5]))->toContain(JenisAnomali::LokasiJauh);
});

test('tanpa foto atau tanpa GPS ditandai bukti tidak lengkap', function () {
    expect(periksa(['adaFoto' => false]))->toContain(JenisAnomali::BuktiTidakLengkap)
        ->and(periksa(['latitude' => null, 'longitude' => null, 'jarakM' => null]))->toBe([JenisAnomali::BuktiTidakLengkap]);
});

test('prabayar dengan indikasi pelanggaran ditandai, tanpa aturan stand', function () {
    $hasil = periksa([
        'jenis' => JenisPelanggan::Prabayar,
        'kondisi' => KondisiMeter::IndikasiPelanggaran,
        'standLalu' => null,
        'standKini' => null,
        'rataTigaBulan' => null,
    ]);

    expect($hasil)->toBe([JenisAnomali::IndikasiPelanggaran]);
});

test('dikirim setelah tanggal selesai periode ditandai lewat jadwal', function () {
    expect(periksa(['dikirimAt' => CarbonImmutable::parse('2026-11-25 23:59')]))->toBe([])
        ->and(periksa(['dikirimAt' => CarbonImmutable::parse('2026-11-26 00:01')]))->toBe([JenisAnomali::LewatJadwal]);
});
