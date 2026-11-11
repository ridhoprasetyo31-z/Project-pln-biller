<?php

namespace App\Services;

use App\Enums\StatusPeriode;
use App\Jobs\HitungSkorKinerja;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus periode: draf → berjalan → ditutup.
 */
class PeriodeService
{
    public function __construct(private readonly PenugasanService $penugasan) {}

    /** Buat periode draf sekaligus menyusun penugasan dari rute. */
    public function buat(string $kode, string $tanggalMulai, string $tanggalSelesai): Periode
    {
        return DB::transaction(function () use ($kode, $tanggalMulai, $tanggalSelesai) {
            $periode = Periode::create([
                'kode' => $kode,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'status' => StatusPeriode::Draft,
            ]);

            $this->penugasan->sinkronDariRute($periode);

            return $periode;
        });
    }

    /**
     * @throws ValidationException
     */
    public function mulai(Periode $periode): void
    {
        $lain = Periode::berjalan();
        if ($lain && $lain->isNot($periode)) {
            throw ValidationException::withMessages([
                'periode' => "Periode {$lain->kode} masih berjalan. Tutup periode tersebut lebih dulu.",
            ]);
        }

        if (! $periode->penugasan()->exists()) {
            throw ValidationException::withMessages([
                'periode' => 'Periode belum punya penugasan. Tugaskan rute ke biller lebih dulu.',
            ]);
        }

        $periode->update(['status' => StatusPeriode::Berjalan]);
    }

    /**
     * Tutup periode. Setelah ditutup, catatan dan penugasan periode ini tidak bisa diubah,
     * lalu skor kinerja periode ini dihitung sebagai skor final.
     */
    public function tutup(Periode $periode, User $oleh): void
    {
        $periode->update([
            'status' => StatusPeriode::Ditutup,
            'ditutup_at' => now(),
            'ditutup_oleh' => $oleh->id,
        ]);

        HitungSkorKinerja::dispatchSync($periode->id, true);
    }
}
