<?php

namespace App\Policies;

use App\Enums\StatusPeriode;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PeriodePolicy extends MasterPolicy
{
    /** @param  Periode  $periode */
    public function update(User $user, Model $periode): bool
    {
        return $this->isSuperAdmin($user) && $periode->bisaDiubah();
    }

    /** Hanya periode draf yang belum punya catatan yang boleh dihapus. @param  Periode  $periode */
    public function delete(User $user, Model $periode): bool
    {
        return $this->isSuperAdmin($user)
            && $periode->status === StatusPeriode::Draft
            && ! $periode->penugasan()->whereHas('pencatatanMeter')->exists();
    }

    public function mulai(User $user, Periode $periode): bool
    {
        return $this->isSuperAdmin($user) && $periode->status === StatusPeriode::Draft;
    }

    public function tutup(User $user, Periode $periode): bool
    {
        return $this->isSuperAdmin($user) && $periode->status === StatusPeriode::Berjalan;
    }
}
