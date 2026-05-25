<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin') || $user->hasRole('super-admin') || $user->role === 'admin') {
            return true;
        }
        return null;
    }

    public function viewAny(User $user)
    {
        return false;
    }

    public function create(User $user)
    {
        return false;
    }

    public function update(User $user, User $model)
    {
        return $user->getKey() === $model->getKey();
    }
}
