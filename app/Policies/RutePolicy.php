<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RutePolicy extends MasterPolicy
{
    /** Koordinator boleh melihat rute di unitnya (dipakai mulai Tahap 3). */
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $user->role === Role::KoordinatorAdmin;
    }

    public function view(User $user, Model $rute): bool
    {
        return $this->isSuperAdmin($user)
            || ($user->role === Role::KoordinatorAdmin && $user->unit_id === $rute->unit_id);
    }
}
