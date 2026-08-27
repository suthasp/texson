<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\StockLevel;
use App\Models\User;

class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::StockViewAny->value);
    }

    public function view(User $user, StockLevel $level): bool
    {
        return $user->can(PermissionName::StockViewAny->value);
    }

    /**
     * ดูประวัติการเคลื่อนไหวย้อนหลัง — แยกสิทธิ์จากการดูยอดคงเหลือ
     */
    public function viewLedger(User $user): bool
    {
        return $user->can(PermissionName::StockViewLedger->value);
    }
}
