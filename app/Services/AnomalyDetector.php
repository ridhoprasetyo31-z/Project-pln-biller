<?php

namespace App\Services;

use App\Enums\JenisAnomali;
use App\Enums\JenisPelanggan;
use App\Enums\KondisiMeter;
use App\Models\Pengaturan;

/**
 * Validasi otomatis saat catatan meter dikirim (PRD bagian 4).
 * Setiap aturan yang terlanggar menghasilkan satu anomali; catatan dengan
 * anomali berstatus "ditandai", tanpa anomali "menunggu_verifikasi".
 */
class AnomalyDetector
{
    /**
     * @param  array{ambang_lonjakan_persen?: int|float, ambang_jarak_m?: int|float}|null  $pengaturan
     */
    public function __construct(private ?array $pengaturan = null) {}

    /**
     * @return list<array{jenis: JenisAnomali, nilai: float|null, keterangan: string}>
     */
    public function periksa(DataPemeriksaan $d): array
    {
        $ambangLonjakan = (float) $this->nilai('ambang_lonjakan_persen');
        $ambangJarak = (float) $this->nilai('ambang_jarak_m');
        $hasil = [];

        if ($d->jenis === JenisPelanggan::Pascabayar) {
            $normal = $d->kondisi === KondisiMeter::Normal;

            if ($normal && $d->standKini !== null && $d->standLalu !== null && $d->standKini < $d->standLalu) {
                $hasil[] = $this->anomali(
                    JenisAnomali::StandTurun,
                    $d->standKini - $d->standLalu,
                    sprintf('Stand kini %s lebih kecil dari stand lalu %s.', $this->angka($d->standKini), $this->angka($d->standLalu)),
                );
            }

            $pemakaian = $d->pemakaian();

            if ($pemakaian !== null && $d->rataTigaBulan !== null && $d->rataTigaBulan > 0) {
                $persen = $pemakaian / $d->rataTigaBulan * 100;
                if ($persen > $ambangLonjakan) {
                    $hasil[] = $this->anomali(
                        JenisAnomali::Lonjakan,
                        round($persen, 1),
                        sprintf(
                            'Pemakaian %s kWh, %s%% dari rata-rata 3 bulan (%s kWh). Batas %s%%.',
                            $this->angka($pemakaian), $this->angka($persen), $this->angka($d->rataTigaBulan), $this->angka($ambangLonjakan),
                        ),
                    );
                }
            }

            if ($normal && $pemakaian !== null && abs($pemakaian) < 0.05) {
                $hasil[] = $this->anomali(JenisAnomali::PemakaianNol, 0, 'Pemakaian 0 kWh padahal kondisi meter normal.');
            }
        }

        if ($d->jarakM !== null && $d->jarakM > $ambangJarak) {
            $hasil[] = $this->anomali(
                JenisAnomali::LokasiJauh,
                round($d->jarakM, 1),
                sprintf('Dicatat %s m dari titik pelanggan. Batas %s m.', $this->angka($d->jarakM, 0), $this->angka($ambangJarak, 0)),
            );
        }

        if (! $d->adaFoto || $d->latitude === null || $d->longitude === null) {
            $kurang = array_filter([! $d->adaFoto ? 'foto' : null, ($d->latitude === null || $d->longitude === null) ? 'lokasi GPS' : null]);
            $hasil[] = $this->anomali(JenisAnomali::BuktiTidakLengkap, null, 'Tanpa '.implode(' dan ', $kurang).'.');
        }

        if ($d->jenis === JenisPelanggan::Prabayar && $d->kondisi === KondisiMeter::IndikasiPelanggaran) {
            $hasil[] = $this->anomali(JenisAnomali::IndikasiPelanggaran, null, 'Biller melaporkan indikasi pelanggaran pada meter prabayar.');
        }

        if ($d->dikirimAt->greaterThan($d->batasPencatatan->copy()->endOfDay())) {
            $hasil[] = $this->anomali(
                JenisAnomali::LewatJadwal,
                null,
                'Dikirim setelah batas pencatatan '.$d->batasPencatatan->locale('id')->translatedFormat('j F Y').'.',
            );
        }

        return $hasil;
    }

    private function nilai(string $kunci): mixed
    {
        return $this->pengaturan[$kunci] ?? Pengaturan::ambil($kunci);
    }

    private function anomali(JenisAnomali $jenis, ?float $nilai, string $keterangan): array
    {
        return ['jenis' => $jenis, 'nilai' => $nilai, 'keterangan' => $keterangan];
    }

    private function angka(float $n, int $desimal = 1): string
    {
        return number_format($n, $desimal, ',', '.');
    }
}
