<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProductViewAny->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProductCreate->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductUpdate->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductDelete->value);
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can(PermissionName::ProductDelete->value);
    }

    /**
     * เห็นราคาทุนและ margin — ใช้ซ่อนคอลัมน์ต้นทุนจาก role ที่ไม่ควรเห็น (spec 4.5)
     */
    public function viewCost(User $user): bool
    {
        return $user->can(PermissionName::ProductViewCost->value);
    }
}
