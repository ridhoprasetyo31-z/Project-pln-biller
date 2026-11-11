<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Pelanggan;
use App\Models\PencatatanMeter;
use App\Models\Penugasan;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PencatatanMeterPolicy
{
    /** Biller hanya boleh mencatat pelanggan di rute yang ditugaskan kepadanya pada periode berjalan. */
    public function catat(User $user, Pelanggan $pelanggan): Response
    {
        if ($user->role !== Role::Biller) {
            return Response::deny('Hanya biller yang bisa mencatat meter.');
        }

        return self::penugasanUntuk($user, $pelanggan)
            ? Response::allow()
            : Response::deny('Pelanggan ini tidak ada di rute yang ditugaskan kepada Anda pada periode berjalan.');
    }

    /** Koordinator memverifikasi catatan dari rute di unitnya. */
    public function verifikasi(User $user, PencatatanMeter $catatan): bool
    {
        return $user->role === Role::KoordinatorAdmin
            && PencatatanMeter::query()->whereKey($catatan->id)->diUnit($user->unit_id)->exists();
    }

    public static function penugasanUntuk(User $user, Pelanggan $pelanggan): ?Penugasan
    {
        $periode = Periode::berjalan();

        return $periode
            ? Penugasan::query()
                ->where('periode_id', $periode->id)
                ->where('rute_id', $pelanggan->rute_id)
                ->where('biller_id', $user->id)
                ->first()
            : null;
    }
}
