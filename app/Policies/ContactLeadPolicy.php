<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ContactLead;
use App\Models\User;

/**
 * คำขอติดต่อเป็นของทีมขายทั้งทีม ไม่ใช่ของใครคนใดคนหนึ่ง
 * ทุกคนที่มีสิทธิ์จึงเห็นทุกใบ — ต่างจากใบเสนอราคาที่ฝ่ายขายเห็นเฉพาะของตัวเอง
 * เพราะคำขอที่เพิ่งเข้ามายังไม่มีเจ้าของ ถ้ากรองตามเจ้าของก็จะไม่มีใครเห็นเลย
 */
class ContactLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::LeadViewAny->value);
    }

    public function view(User $user, ContactLead $lead): bool
    {
        return $user->can(PermissionName::LeadView->value);
    }

    public function update(User $user, ContactLead $lead): bool
    {
        return $this->updateAny($user) && $lead->status->isOpen();
    }

    /**
     * คู่แฝดที่ดูแค่สิทธิ์ ไม่ดูสถานะ (ADR-014)
     * ฝั่ง API ใช้ตัวนี้เพื่อให้ "สถานะไม่ถูกต้อง" ตอบ 409 ไม่ใช่ 403
     */
    public function updateAny(User $user): bool
    {
        return $user->can(PermissionName::LeadUpdate->value);
    }

    public function delete(User $user, ContactLead $lead): bool
    {
        return $user->can(PermissionName::LeadDelete->value);
    }

    public function restore(User $user, ContactLead $lead): bool
    {
        return $user->can(PermissionName::LeadDelete->value);
    }
}
