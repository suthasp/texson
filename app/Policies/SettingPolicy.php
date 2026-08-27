<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Setting;
use App\Models\User;

/**
 * ค่าตั้งระบบ — ดูได้หลายคน แก้ได้เฉพาะผู้ดูแลระบบ
 *
 * เกณฑ์อนุมัติอยู่ในนี้ด้วย ถ้าใครแก้ได้ก็เท่ากับข้ามการอนุมัติได้
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SettingViewAny->value);
    }

    public function view(User $user, Setting $setting): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $user->can(PermissionName::SettingUpdate->value);
    }
}
