<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Quotation;
use App\Models\User;

/**
 * สิทธิ์ใบเสนอราคา
 *
 * กฎหลักสองชั้น
 *  1. ต้องมี permission ของการกระทำนั้น
 *  2. sales เห็นและแก้ได้เฉพาะใบของตัวเอง (spec 8) — admin/ผู้จัดการฝ่ายขายเห็นทุกใบ
 *
 * และซ้อนด้วยกฎสถานะ: ใบที่ส่งออกไปแล้วแก้ไม่ได้ ต้องสร้าง revision
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::QuotationViewAny->value);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationView->value)
            && $this->owns($user, $quotation);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::QuotationCreate->value);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationUpdate->value)
            && $this->owns($user, $quotation)
            && $quotation->status->isEditable();
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationDelete->value)
            && $this->owns($user, $quotation)
            && $quotation->status->isEditable();
    }

    public function submit(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationSubmit->value)
            && $this->owns($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::PendingApproval);
    }

    /**
     * ผู้อนุมัติต้องไม่ใช่เจ้าของใบ — กันการอนุมัติส่วนลดให้ตัวเอง
     * ยกเว้น admin ที่ต้องปลดล็อกระบบได้ในกรณีคนเดียวทำทั้งบริษัท
     */
    public function approve(User $user, Quotation $quotation): bool
    {
        if (! $user->can(PermissionName::QuotationApprove->value)) {
            return false;
        }

        if ($quotation->status !== QuotationStatus::PendingApproval) {
            return false;
        }

        return $quotation->sales_user_id !== $user->id
            || $user->hasRole(RoleName::Admin->value);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationSend->value)
            && $this->owns($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::Sent);
    }

    /**
     * บันทึกผลจากลูกค้า — ทำได้เฉพาะใบที่ส่งไปแล้ว
     */
    public function decide(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationDecide->value)
            && $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Sent;
    }

    /**
     * ยกเลิกใบ — ทำได้ตั้งแต่ร่างไปจนถึงใบที่ส่งแล้ว จึงใช้กฎสถานะของ enum ตรง ๆ
     * ไม่ผูกกับ update() ที่อนุญาตเฉพาะใบที่ยังแก้ไขได้
     */
    public function cancel(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationUpdate->value)
            && $this->owns($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::Cancelled);
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationRevise->value)
            && $this->owns($user, $quotation)
            && $quotation->status->canBeRevised();
    }

    /**
     * พิมพ์ PDF ได้ทุกใบที่เปิดดูได้ — เอกสารที่เห็นบนจอกับที่พิมพ์ต้องเป็นชุดเดียวกัน
     */
    public function print(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation);
    }

    private function owns(User $user, Quotation $quotation): bool
    {
        return $user->seesAllDocuments() || $quotation->sales_user_id === $user->id;
    }
}
