<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::WarehouseViewAny->value);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can(PermissionName::WarehouseViewAny->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::WarehouseCreate->value);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can(PermissionName::WarehouseUpdate->value);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can(PermissionName::WarehouseDelete->value);
    }
}
