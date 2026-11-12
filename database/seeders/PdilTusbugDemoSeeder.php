<?php

namespace Database\Seeders;

use App\Enums\AlasanTolak;
use App\Models\Pdil;
use App\Models\Pelanggan;
use App\Models\Rute;
use App\Models\Tusbug;
use App\Models\User;
use App\Services\PdilService;
use App\Services\TusbugService;
use App\Services\VerificationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Contoh TUSBUG (ditugaskan, dilaksanakan, disetujui → pelanggan diputus) dan
 * PDIL (pelanggan baru menunggu, perubahan data disetujui, satu dikembalikan),
 * dibuat lewat service sungguhan sehingga notifikasi lonceng ikut terbentuk.
 */
class PdilTusbugDemoSeeder extends Seeder
{
    public function run(TusbugService $tusbug, PdilService $pdil, VerificationService $verifikasi): void
    {
        // Idempoten: jangan buat ulang bila contoh sudah ada.
        if (Tusbug::exists() || Pdil::exists()) {
            return;
        }

        $rina = User::where('email', 'koordinator@biller.test')->first();
        $andi = User::where('email', 'biller@biller.test')->first();
        $dewi = User::where('email', 'dewi@biller.test')->first();
        if (! $rina || ! $andi || ! $dewi) {
            return;
        }

        FotoDemo::pastikanAda();
        $foto = fn (int $i = 0) => new UploadedFile(Storage::disk('public')->path(FotoDemo::METER[$i % count(FotoDemo::METER)]), 'foto.jpg', 'image/jpeg', null, true);

        // ---- TUSBUG: tugaskan 6 saran teratas ULP Panam ke biller pemegang rute masing-masing.
        $saran = $tusbug->saran($rina->unit_id)->take(6);
        if ($saran->isNotEmpty()) {
            $tugas = $tusbug->tugaskan($rina, $saran->pluck('pelanggan_id')->all(), null, now()->addDays(2)->toDateString());

            $milikAndi = $tugas->where('biller_id', $andi->id)->values();
            if ($milikAndi->count() >= 2) {
                // Dilaksanakan: diputus, lalu disetujui → pelanggan putus sementara.
                $t1 = $milikAndi[0]->load('pelanggan');
                $tusbug->laksanakan($andi, $t1, [
                    'hasil' => 'diputus',
                    'keterangan' => 'MCB dicabut dan disegel, pelanggan sudah diberi surat.',
                    'latitude' => $t1->pelanggan->latitude + 10 / 111_320,
                    'longitude' => $t1->pelanggan->longitude,
                ], ['sebelum' => $foto(0), 'sesudah' => $foto(1)]);
                if ($t1->fresh()->status->value === 'menunggu_verifikasi') {
                    $verifikasi->setujui($t1->fresh(), $rina);
                }

                // Dilaksanakan: pelanggan bayar di tempat, menunggu verifikasi koordinator.
                $t2 = $milikAndi[1]->load('pelanggan');
                $tusbug->laksanakan($andi, $t2, [
                    'hasil' => 'bayar_di_tempat',
                    'keterangan' => 'Pelanggan melunasi semua tunggakan saat petugas datang.',
                    'latitude' => $t2->pelanggan->latitude,
                    'longitude' => $t2->pelanggan->longitude + 8 / 111_320,
                ], ['sebelum' => $foto(2), 'sesudah' => $foto(3)]);
            }
        }

        // ---- PDIL
        $rutePnm01 = Rute::where('kode', 'PNM-01')->first();
        if ($rutePnm01) {
            // Pelanggan baru di ujung rute PNM-01, menunggu verifikasi.
            $ujung = Pelanggan::where('rute_id', $rutePnm01->id)->orderByDesc('urutan_rute')->first();
            $pdil->ajukan($andi, [
                'jenis' => 'pelanggan_baru',
                'rute_id' => $rutePnm01->id,
                'nama' => 'Warung Makan Bu Sari',
                'alamat' => 'Jl. Purwodadi No. 62',
                'kelurahan' => $rutePnm01->kelurahan,
                'jenis_pelanggan' => 'pascabayar',
                'golongan_tarif' => 'B1',
                'daya_va' => 2200,
                'nomor_meter' => '45119990001',
                'letak_meter' => 'Dinding samping kiri',
                'latitude' => $ujung->latitude + 0.0002,
                'longitude' => $ujung->longitude + 0.0001,
                'keterangan' => 'Sambungan baru, meter sudah terpasang minggu lalu.',
            ], ['rumah' => $foto(0), 'meter' => $foto(1)]);

            // Perubahan data disetujui: daya naik dan letak meter pindah.
            $ubah = Pelanggan::where('rute_id', $rutePnm01->id)->where('status', 'aktif')->where('daya_va', 900)->orderBy('urutan_rute')->first();
            if ($ubah) {
                $data = PdilService::dataPelanggan($ubah);
                $usul = $pdil->ajukan($andi, ['jenis' => 'perubahan_data', 'pelanggan_id' => $ubah->id]
                    + array_merge($data, ['daya_va' => 1300, 'letak_meter' => 'Pagar depan', 'keterangan' => 'Pelanggan sudah tambah daya ke 1.300 VA.']),
                    ['rumah' => $foto(2), 'meter' => $foto(3)]);
                if ($usul->status->value === 'menunggu_verifikasi') {
                    $verifikasi->setujui($usul, $rina);
                }
            }
        }

        // Perubahan data Dewi yang dikembalikan untuk revisi (memicu notifikasi ke Dewi).
        $pelDewi = Pelanggan::whereIn('rute_id', Rute::where('biller_id', $dewi->id)->select('id'))->where('status', 'aktif')->orderBy('urutan_rute')->first();
        if ($pelDewi) {
            $data = PdilService::dataPelanggan($pelDewi);
            $usul = $pdil->ajukan($dewi, ['jenis' => 'perubahan_data', 'pelanggan_id' => $pelDewi->id]
                + array_merge($data, ['nama' => $data['nama'].' (Toko)']), ['rumah' => $foto(1), 'meter' => $foto(2)]);
            $verifikasi->tolak($usul, $rina, AlasanTolak::DataTidakLengkap, 'Lampirkan foto KTP atau surat keterangan perubahan nama.');
        }
    }
}
