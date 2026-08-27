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
 *
 * ── ทำไมทุก ability มีคู่แฝดลงท้าย ...Any ──
 * ตัวธรรมดา (submit, send, ...) รวมกฎสถานะไว้ด้วย — หน้าเว็บใช้ตัวนี้ซ่อน/โชว์ปุ่ม
 * ตัวลงท้าย ...Any ตรวจแค่ permission กับความเป็นเจ้าของ — API ใช้ตัวนี้
 * เพื่อให้ "สถานะผิด" ตกไปเป็น 409 จาก service ตามสเปกข้อ 6 ไม่ใช่ 403
 * ที่สื่อผิดว่าผู้เรียกไม่มีสิทธิ์
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

    // ── แก้ไข ───────────────────────────────────────────────

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->updateAny($user, $quotation) && $quotation->status->isEditable();
    }

    public function updateAny(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationUpdate->value)
            && $this->owns($user, $quotation);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationDelete->value)
            && $this->owns($user, $quotation)
            && $quotation->status->isEditable();
    }

    // ── ส่งขออนุมัติ ────────────────────────────────────────

    public function submit(User $user, Quotation $quotation): bool
    {
        return $this->submitAny($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::PendingApproval);
    }

    public function submitAny(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationSubmit->value)
            && $this->owns($user, $quotation);
    }

    // ── อนุมัติ ─────────────────────────────────────────────

    public function approve(User $user, Quotation $quotation): bool
    {
        return $this->approveAny($user, $quotation)
            && $quotation->status === QuotationStatus::PendingApproval;
    }

    /**
     * ผู้อนุมัติต้องไม่ใช่เจ้าของใบ — กันการอนุมัติส่วนลดให้ตัวเอง
     * ยกเว้น admin ที่ต้องปลดล็อกระบบได้ในกรณีคนเดียวทำทั้งบริษัท
     *
     * ข้อห้ามนี้อยู่ในชั้น "สิทธิ์" ไม่ใช่ชั้น "สถานะ" จึงตอบ 403 ทั้งบนเว็บและ API
     */
    public function approveAny(User $user, Quotation $quotation): bool
    {
        if (! $user->can(PermissionName::QuotationApprove->value)) {
            return false;
        }

        return $quotation->sales_user_id !== $user->id
            || $user->hasRole(RoleName::Admin->value);
    }

    // ── ส่งให้ลูกค้า ────────────────────────────────────────

    public function send(User $user, Quotation $quotation): bool
    {
        return $this->sendAny($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::Sent);
    }

    public function sendAny(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationSend->value)
            && $this->owns($user, $quotation);
    }

    // ── บันทึกผลจากลูกค้า ───────────────────────────────────

    public function decide(User $user, Quotation $quotation): bool
    {
        return $this->decideAny($user, $quotation)
            && $quotation->status === QuotationStatus::Sent;
    }

    public function decideAny(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationDecide->value)
            && $this->owns($user, $quotation);
    }

    // ── ยกเลิก ──────────────────────────────────────────────

    /**
     * ยกเลิกใบ — ทำได้ตั้งแต่ร่างไปจนถึงใบที่ส่งแล้ว จึงใช้กฎสถานะของ enum ตรง ๆ
     * ไม่ผูกกับ update() ที่อนุญาตเฉพาะใบที่ยังแก้ไขได้
     */
    public function cancel(User $user, Quotation $quotation): bool
    {
        return $this->updateAny($user, $quotation)
            && $quotation->status->canTransitionTo(QuotationStatus::Cancelled);
    }

    // ── สร้างฉบับแก้ไข ──────────────────────────────────────

    public function revise(User $user, Quotation $quotation): bool
    {
        return $this->reviseAny($user, $quotation) && $quotation->status->canBeRevised();
    }

    public function reviseAny(User $user, Quotation $quotation): bool
    {
        return $user->can(PermissionName::QuotationRevise->value)
            && $this->owns($user, $quotation);
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
