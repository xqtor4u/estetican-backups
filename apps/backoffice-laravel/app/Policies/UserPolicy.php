<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super-admin') || $user->role === 'admin';
    }

    public function viewAny(User $user)
    {
        return $this->isAdmin($user);
    }

    public function create(User $user)
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $model)
    {
        return $user->getKey() === $model->getKey() || $this->isAdmin($user);
    }
}
