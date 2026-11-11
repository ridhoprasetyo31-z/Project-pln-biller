<?php

namespace App\Policies;

use App\Models\Penugasan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PenugasanPolicy extends MasterPolicy
{
    /** Penugasan di periode yang sudah ditutup tidak bisa diubah. @param  Penugasan  $penugasan */
    public function update(User $user, Model $penugasan): bool
    {
        return $this->isSuperAdmin($user) && $penugasan->loadMissing('periode')->periode->bisaDiubah();
    }

    /** @param  Penugasan  $penugasan */
    public function delete(User $user, Model $penugasan): bool
    {
        return $this->update($user, $penugasan) && ! $penugasan->pencatatanMeter()->exists();
    }
}
