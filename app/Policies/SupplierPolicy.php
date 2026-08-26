<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SupplierViewAny->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(PermissionName::SupplierView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SupplierCreate->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(PermissionName::SupplierUpdate->value);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(PermissionName::SupplierDelete->value);
    }
}
