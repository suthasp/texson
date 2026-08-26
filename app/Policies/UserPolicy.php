<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UserViewAny->value);
    }

    public function view(User $user, User $target): bool
    {
        return $user->can(PermissionName::UserView->value) || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UserCreate->value);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(PermissionName::UserUpdate->value);
    }

    /**
     * กันไม่ให้ลบบัญชีตัวเองจนไม่มีใครเข้าระบบได้
     */
    public function delete(User $user, User $target): bool
    {
        return $user->can(PermissionName::UserDelete->value) && ! $user->is($target);
    }
}
