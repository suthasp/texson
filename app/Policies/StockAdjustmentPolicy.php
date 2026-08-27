<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::StockAdjustmentViewAny->value);
    }

    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(PermissionName::StockAdjustmentView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::StockAdjustmentCreate->value);
    }

    public function update(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(PermissionName::StockAdjustmentUpdate->value) && $adjustment->status->isEditable();
    }

    public function post(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(PermissionName::StockAdjustmentPost->value) && $adjustment->status->isEditable();
    }

    public function delete(User $user, StockAdjustment $adjustment): bool
    {
        return $user->can(PermissionName::StockAdjustmentDelete->value) && $adjustment->status->isEditable();
    }
}
