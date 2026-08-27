<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\SerialNumber;
use App\Models\User;

class SerialNumberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SerialViewAny->value);
    }

    public function view(User $user, SerialNumber $serial): bool
    {
        return $user->can(PermissionName::SerialView->value);
    }

    public function update(User $user, SerialNumber $serial): bool
    {
        return $user->can(PermissionName::SerialUpdate->value);
    }
}
