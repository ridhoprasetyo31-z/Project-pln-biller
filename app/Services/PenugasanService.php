<?php

namespace App\Services;

use App\Enums\StatusPelanggan;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\Rute;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun penugasan (rute → biller) untuk satu periode.
 */
class PenugasanService
{
    /**
     * Buat/perbarui penugasan untuk semua rute aktif yang punya biller pemegang.
     * Target = jumlah pelanggan aktif di rute. Penugasan yang sudah ada
     * hanya diperbarui targetnya, biller pilihan admin tidak ditimpa.
     *
     * @return array{dibuat: int, diperbarui: int, dilewati: int}
     */
    public function sinkronDariRute(Periode $periode): array
    {
        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0];

        $rute = Rute::query()
            ->aktif()
            ->withCount(['pelanggan as pelanggan_aktif_count' => fn ($q) => $q->where('status', StatusPelanggan::Aktif)])
            ->get();

        $ada = Penugasan::where('periode_id', $periode->id)->get()->keyBy('rute_id');

        DB::transaction(function () use ($rute, $ada, $periode, &$hasil) {
            foreach ($rute as $r) {
                $existing = $ada->get($r->id);

                if ($existing) {
                    $existing->update(['target_pelanggan' => $r->pelanggan_aktif_count]);
                    $hasil['diperbarui']++;

                    continue;
                }

                if (! $r->biller_id) {
                    $hasil['dilewati']++;

                    continue;
                }

                Penugasan::create([
                    'periode_id' => $periode->id,
                    'rute_id' => $r->id,
                    'biller_id' => $r->biller_id,
                    'target_pelanggan' => $r->pelanggan_aktif_count,
                ]);
                $hasil['dibuat']++;
            }
        });

        return $hasil;
    }
}
