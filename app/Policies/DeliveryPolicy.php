<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\StockDocumentStatus;
use App\Models\Delivery;
use App\Models\User;

/**
 * สิทธิ์ใบส่งของ
 *
 * ใบที่ post แล้วแก้และลบไม่ได้ ต่อให้มีสิทธิ์ก็ตาม — ledger เป็น append-only
 * เหมือนเอกสารคลังอื่นใน Phase 2
 */
class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::DeliveryViewAny->value);
    }

    public function view(User $user, Delivery $delivery): bool
    {
        return $user->can(PermissionName::DeliveryView->value)
            && $this->owns($user, $delivery);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::DeliveryCreate->value);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return $this->updateAny($user, $delivery) && $delivery->status->isEditable();
    }

    public function updateAny(User $user, Delivery $delivery): bool
    {
        return $user->can(PermissionName::DeliveryUpdate->value)
            && $this->owns($user, $delivery);
    }

    public function post(User $user, Delivery $delivery): bool
    {
        return $this->postAny($user, $delivery)
            && $delivery->status->canTransitionTo(StockDocumentStatus::Posted);
    }

    public function postAny(User $user, Delivery $delivery): bool
    {
        return $user->can(PermissionName::DeliveryPost->value);
    }

    public function delete(User $user, Delivery $delivery): bool
    {
        return $user->can(PermissionName::DeliveryDelete->value)
            && $this->owns($user, $delivery)
            && $delivery->status->isEditable();
    }

    /**
     * คนคลังเห็นใบส่งของทุกใบเพราะเป็นคนจัดของ
     * ฝ่ายขายเห็นเฉพาะใบที่มาจากใบสั่งขายของตัวเอง (spec 8)
     */
    private function owns(User $user, Delivery $delivery): bool
    {
        if ($user->seesAllDocuments() || $user->can(PermissionName::DeliveryPost->value)) {
            return true;
        }

        return $delivery->salesOrder()->value('sales_user_id') === $user->id;
    }
}
