<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CustomerViewAny->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::CustomerCreate->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerUpdate->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerDelete->value);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerDelete->value);
    }

    /**
     * ลบถาวรตามคำขอ PDPA — ทำได้เฉพาะ admin (spec 8)
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerForceDelete->value);
    }

    /**
     * ส่งออกข้อมูลลูกค้ารายคนตามสิทธิ์ PDPA (spec 8)
     */
    public function export(User $user, Customer $customer): bool
    {
        return $user->can(PermissionName::CustomerExport->value);
    }
}
