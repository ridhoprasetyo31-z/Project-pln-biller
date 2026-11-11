<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PelangganPolicy extends MasterPolicy
{
    /** Koordinator boleh melihat pelanggan di unitnya (dipakai mulai Tahap 3). */
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->role === Role::KoordinatorAdmin;
    }

    public function view(User $user, Model $pelanggan): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->role === Role::KoordinatorAdmin
            && $pelanggan->rute()->where('unit_id', $user->unit_id)->exists();
    }

    public function import(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }
}
