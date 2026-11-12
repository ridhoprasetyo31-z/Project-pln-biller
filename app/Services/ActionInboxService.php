<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatusCatatan;
use App\Enums\StatusTusbug;
use App\Models\KoreksiRekening;
use App\Models\KunjunganPenagihan;
use App\Models\Pdil;
use App\Models\PencatatanMeter;
use App\Models\Tusbug;
use App\Models\User;

/**
 * Jumlah pekerjaan yang menunggu tindakan pengguna, untuk badge merah di menu.
 * Kunci hasil = id item navigasi (resources/js/lib/navigation.ts).
 */
class ActionInboxService
{
    private const MENUNGGU = ['menunggu_verifikasi', 'ditandai'];

    /** @return array<string, int> */
    public function badges(User $user): array
    {
        return match ($user->role) {
            Role::Biller => [
                'revisi' => PencatatanMeter::where('biller_id', $user->id)->where('status', StatusCatatan::Revisi)->periodeTerbuka()->count()
                    + KunjunganPenagihan::where('biller_id', $user->id)->where('status', StatusCatatan::Revisi)->count()
                    + KoreksiRekening::where('diajukan_oleh', $user->id)->where('status', StatusCatatan::Revisi)->count()
                    + Pdil::where('biller_id', $user->id)->where('status', StatusCatatan::Revisi)->count(),
                'tusbug' => Tusbug::where('biller_id', $user->id)
                    ->whereIn('status', [StatusTusbug::Ditugaskan, StatusTusbug::Revisi])
                    ->count(),
            ],
            Role::KoordinatorAdmin => [
                'verifikasi' => PencatatanMeter::query()->diUnit($user->unit_id)->menungguVerifikasi()->periodeTerbuka()->count()
                    + KunjunganPenagihan::query()->diUnit($user->unit_id)->whereIn('status', self::MENUNGGU)->count()
                    + KoreksiRekening::query()->diUnit($user->unit_id)->whereIn('status', self::MENUNGGU)->count()
                    + Pdil::query()->diUnit($user->unit_id)->whereIn('status', self::MENUNGGU)->count()
                    + Tusbug::query()->diUnit($user->unit_id)->whereIn('status', self::MENUNGGU)->count(),
            ],
            default => [],
        };
    }
}
