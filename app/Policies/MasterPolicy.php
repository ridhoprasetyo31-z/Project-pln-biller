<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar policy data master: hanya super admin yang boleh mengelola.
 */
abstract class MasterPolicy
{
    protected function isSuperAdmin(User $user): bool
    {
        return $user->role === Role::SuperAdmin;
    }

    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->isSuperAdmin($user);
    }
}
