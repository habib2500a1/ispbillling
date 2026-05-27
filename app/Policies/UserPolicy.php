<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('staff.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('staff.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, User $model): bool
    {
        // No one can delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('isp-admin')) {
            return !$model->hasRole('super-admin');
        }

        return $user->can('staff.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->can('staff.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}