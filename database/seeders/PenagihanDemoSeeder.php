<?php

namespace Database\Seeders;

use App\Enums\AlasanTolak;
use App\Enums\Role;
use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\User;
use App\Services\KoreksiService;
use App\Services\KunjunganService;
use App\Services\VerificationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Contoh kunjungan penagihan dan koreksi rekening, dibuat lewat service sungguhan
 * agar status, anomali, invoice, dan skor konsisten dengan aturan aplikasi.
 */
class PenagihanDemoSeeder extends Seeder
{
    public function run(KunjunganService $kunjungan, KoreksiService $koreksi, VerificationService $verifikasi): void
    {
        // Idempoten: jangan buat ulang bila contoh sudah ada.
        if (KunjunganPenagihan::exists() || KoreksiRekening::exists()) {
            return;
        }

        $andi = User::where('email', 'biller@biller.test')->first();
        $dewi = User::where('email', 'dewi@biller.test')->first();
        $rina = User::where('role', Role::KoordinatorAdmin)->where('email', 'koordinator@biller.test')->first();
        if (! $andi || ! $dewi || ! $rina) {
            return;
        }

        FotoDemo::pastikanAda();
        $foto = fn () => new UploadedFile(Storage::disk('public')->path(FotoDemo::METER[0]), 'bukti.jpg', 'image/jpeg', null, true);
        $dekat = fn (Invoice $i, int $meter) => [
            'latitude' => $i->pelanggan->latitude + $meter / 111_320,
            'longitude' => $i->pelanggan->longitude,
        ];

        $tagihan = fn (User $biller, StatusInvoice $status) => Invoice::query()
            ->terlihatOleh($biller)
            ->where('status', $status)
            ->with('pelanggan')
            ->orderBy('id');

        // Kunjungan Andi: janji bayar (menunggu), lunas (disetujui → invoice lunas), tidak bertemu tapi lokasi jauh (ditandai).
        $menunggak = $tagihan($andi, StatusInvoice::Menunggak)->limit(3)->get();
        if ($menunggak->count() === 3) {
            $kunjungan->simpan($andi, $menunggak[0], ['hasil' => 'janji_bayar', 'tanggal_janji' => now()->addDays(5)->toDateString(), 'keterangan' => 'Pelanggan berjanji bayar setelah gajian.'] + $dekat($menunggak[0], 12), $foto());

            $lunas = $kunjungan->simpan($andi, $menunggak[1], ['hasil' => 'lunas', 'nominal_dibayar' => $menunggak[1]->total] + $dekat($menunggak[1], 8), $foto());
            if ($lunas->status->value === 'menunggu_verifikasi') {
                $verifikasi->setujui($lunas, $rina);
            }

            $kunjungan->simpan($andi, $menunggak[2], ['hasil' => 'tidak_bertemu', 'keterangan' => 'Rumah kosong, pintu terkunci.'] + $dekat($menunggak[2], 260), $foto());
        }

        // Koreksi: Andi salah catat (menunggu koordinator), Dewi karena komplain pelanggan (sudah disetujui),
        // dan satu koreksi Andi dikembalikan untuk revisi.
        $belumAndi = $tagihan($andi, StatusInvoice::BelumBayar)->with('pencatatanMeter')->limit(2)->get();
        if ($belumAndi->count() === 2) {
            $c = $belumAndi[0]->pencatatanMeter;
            $koreksi->ajukan($andi, $belumAndi[0], [
                'stand_baru' => round($c->stand_kini - 12, 1),
                'penyebab' => 'salah_catat_biller',
                'alasan' => 'Digit terakhir terbaca 8, seharusnya 6. Sudah dicek ulang ke rumah pelanggan.',
            ], $foto());

            $c2 = $belumAndi[1]->pencatatanMeter;
            $dikembalikan = $koreksi->ajukan($andi, $belumAndi[1], [
                'stand_baru' => round($c2->stand_kini + 30, 1),
                'penyebab' => 'salah_input_sistem',
                'alasan' => 'Angka di sistem berbeda dengan foto meter.',
            ], $foto());
            $verifikasi->tolak($dikembalikan, $rina, AlasanTolak::DataTidakLengkap, 'Lampirkan foto meter terbaru yang jelas, angka di foto tidak terbaca.');
        }

        $belumDewi = $tagihan($dewi, StatusInvoice::BelumBayar)->with('pencatatanMeter')->first();
        if ($belumDewi) {
            $c = $belumDewi->pencatatanMeter;
            $k = $koreksi->ajukan($dewi, $belumDewi, [
                'stand_baru' => round($c->stand_kini - 20, 1),
                'penyebab' => 'komplain_pelanggan',
                'alasan' => 'Pelanggan komplain tagihan naik, dicek ulang stand meter lebih kecil 20 kWh.',
            ], $foto());
            $verifikasi->setujui($k, $rina);
        }
    }
}
