<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::BrandViewAny->value);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can(PermissionName::BrandViewAny->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::BrandCreate->value);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can(PermissionName::BrandUpdate->value);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can(PermissionName::BrandDelete->value);
    }
}
