<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tusbug;
use App\Models\User;

class TusbugPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Biller, Role::KoordinatorAdmin], true);
    }

    public function view(User $user, Tusbug $tusbug): bool
    {
        return match ($user->role) {
            Role::Biller => $tusbug->biller_id === $user->id,
            Role::KoordinatorAdmin => Tusbug::query()->whereKey($tusbug->id)->diUnit($user->unit_id)->exists(),
            default => false,
        };
    }

    /** Koordinator menugaskan TUSBUG di unitnya. */
    public function create(User $user): bool
    {
        return $user->role === Role::KoordinatorAdmin;
    }

    /** Biller pelaksana mengisi hasil saat tugas masih terbuka untuknya. */
    public function laksanakan(User $user, Tusbug $tusbug): bool
    {
        return $user->role === Role::Biller && $tusbug->biller_id === $user->id && $tusbug->bisaDilaksanakan();
    }

    /** Koordinator membatalkan tugas yang belum dilaksanakan. */
    public function delete(User $user, Tusbug $tusbug): bool
    {
        return $user->role === Role::KoordinatorAdmin
            && $tusbug->status->value === 'ditugaskan'
            && $this->view($user, $tusbug);
    }

    public function verifikasi(User $user, Tusbug $tusbug): bool
    {
        return $user->role === Role::KoordinatorAdmin && $this->view($user, $tusbug);
    }
}
