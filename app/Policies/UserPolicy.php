<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends MasterPolicy
{
    /** Super admin tidak boleh menghapus akunnya sendiri. */
    public function delete(User $user, Model $model): bool
    {
        return $this->isSuperAdmin($user) && $user->isNot($model);
    }
}
