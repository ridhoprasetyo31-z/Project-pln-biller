<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\User;
use App\Services\PenugasanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Periode 202604–202609 (ditutup, untuk riwayat pemakaian dan skor) dan
 * 202610 (berjalan), lengkap dengan penugasan.
 *
 * Jendela baca meter periode YYYYMM: tanggal 26 bulan itu sampai tanggal 25
 * bulan berikutnya. Batas periode berjalan dibuat minimal 14 hari dari hari ini
 * agar demo pencatatan tidak otomatis "lewat jadwal".
 */
class PeriodeSeeder extends Seeder
{
    public const DITUTUP = ['202604', '202605', '202606', '202607', '202608', '202609'];

    public const BERJALAN = '202610';

    public function run(PenugasanService $penugasan): void
    {
        $admin = User::where('role', Role::SuperAdmin)->first();

        foreach ([...self::DITUTUP, self::BERJALAN] as $kode) {
            $bulan = CarbonImmutable::createFromFormat('Ym', $kode)->startOfMonth();
            $mulai = $bulan->setDay(26);
            $selesai = $bulan->addMonthNoOverflow()->setDay(25);
            $berjalan = $kode === self::BERJALAN;

            if ($berjalan) {
                $minimal = CarbonImmutable::today()->addDays(14);
                $selesai = $selesai->max($minimal);
            }

            $periode = Periode::updateOrCreate(['kode' => $kode], [
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'status' => $berjalan ? StatusPeriode::Berjalan : StatusPeriode::Ditutup,
                'ditutup_at' => $berjalan ? null : $selesai->addDays(2)->setTime(9, 0),
                'ditutup_oleh' => $berjalan ? null : $admin?->id,
            ]);

            $penugasan->sinkronDariRute($periode);
        }
    }
}
