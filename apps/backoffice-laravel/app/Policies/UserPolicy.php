<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Permite ver cualquier usuario: solo super admin
    public function viewAny(User $user)
    {
        return $user->is_super_admin;
    }

    // Permite crear usuarios: solo super admin
    public function create(User $user)
    {
        return $user->is_super_admin;
    }

    // Permite editar: super admin o el propio usuario
    public function update(User $user, User $model)
    {
        return $user->getKey() === $model->getKey() || $user->is_super_admin;
    }
}
