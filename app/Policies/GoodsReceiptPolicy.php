<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::GoodsReceiptViewAny->value);
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can(PermissionName::GoodsReceiptView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::GoodsReceiptCreate->value);
    }

    /**
     * ใบที่ post แล้วแก้ไม่ได้ ต่อให้มีสิทธิ์ก็ตาม — ledger เป็น append-only
     */
    public function update(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can(PermissionName::GoodsReceiptUpdate->value) && $receipt->status->isEditable();
    }

    public function post(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can(PermissionName::GoodsReceiptPost->value) && $receipt->status->isEditable();
    }

    public function delete(User $user, GoodsReceipt $receipt): bool
    {
        return $user->can(PermissionName::GoodsReceiptDelete->value) && $receipt->status->isEditable();
    }
}
