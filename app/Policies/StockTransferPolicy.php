<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::StockTransferViewAny->value);
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->can(PermissionName::StockTransferView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::StockTransferCreate->value);
    }

    public function update(User $user, StockTransfer $transfer): bool
    {
        return $user->can(PermissionName::StockTransferUpdate->value) && $transfer->status->isEditable();
    }

    public function post(User $user, StockTransfer $transfer): bool
    {
        return $user->can(PermissionName::StockTransferPost->value) && $transfer->status->isEditable();
    }

    public function delete(User $user, StockTransfer $transfer): bool
    {
        return $user->can(PermissionName::StockTransferDelete->value) && $transfer->status->isEditable();
    }
}
