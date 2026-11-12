<?php

namespace Database\Seeders;

use App\Enums\StatusPeriode;
use App\Jobs\TandaiInvoiceMenunggak;
use App\Models\Periode;
use App\Services\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Terbitkan invoice dari semua catatan pascabayar yang disetujui, lalu simulasikan pembayaran:
 * - pelanggan "penunggak kronis" (±4%) tidak membayar sejak periode 202606 → 3 lembar menunggak;
 * - pelanggan lain umumnya membayar sebelum jatuh tempo, sebagian kecil lupa satu bulan;
 * - invoice yang jatuh temponya belum lewat sebagian sudah dibayar.
 */
class InvoiceSeeder extends Seeder
{
    public function run(InvoiceGenerator $generator): void
    {
        // Idempoten: simulasi pembayaran hanya sekali.
        if (DB::table('invoice')->exists()) {
            return;
        }

        mt_srand(20261113);
        $hariIni = CarbonImmutable::today();

        $periode = Periode::where('status', '!=', StatusPeriode::Draft)->orderBy('kode')->get();
        DB::transaction(function () use ($periode, $generator) {
            foreach ($periode as $p) {
                $generator->buatPeriode($p);
            }
        });

        $invoice = DB::table('invoice as i')
            ->join('periode as p', 'p.id', '=', 'i.periode_id')
            ->where('i.status', 'belum_bayar')
            ->get(['i.id', 'i.pelanggan_id', 'i.jatuh_tempo', 'p.kode', 'p.tanggal_selesai']);

        $lunas = [];
        foreach ($invoice as $i) {
            $jatuhTempo = CarbonImmutable::parse($i->jatuh_tempo);
            $kronis = $i->pelanggan_id % 25 === 3 && $i->kode >= '202606';

            if ($kronis) {
                continue;
            }

            $sudahJatuhTempo = $jatuhTempo->lessThan($hariIni);
            $bayar = $sudahJatuhTempo ? mt_rand(1, 100) <= 97 : mt_rand(1, 100) <= 55;
            if (! $bayar) {
                continue;
            }

            $mulai = CarbonImmutable::parse($i->tanggal_selesai)->addDay();
            $akhir = $sudahJatuhTempo ? $jatuhTempo : $hariIni->subDay();
            $hari = max(0, (int) $mulai->diffInDays($akhir));
            $lunas[$i->id] = $mulai->addDays(mt_rand(0, $hari))->setTime(mt_rand(8, 20), mt_rand(0, 59));
        }

        DB::transaction(function () use ($lunas) {
            foreach ($lunas as $id => $waktu) {
                DB::table('invoice')->where('id', $id)->update(['status' => 'lunas', 'dibayar_at' => $waktu, 'updated_at' => $waktu]);
            }
        });

        (new TandaiInvoiceMenunggak)->handle();
    }
}
