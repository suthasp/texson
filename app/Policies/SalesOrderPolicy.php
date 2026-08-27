<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

/**
 * สิทธิ์ใบสั่งขาย
 *
 * โครงเดียวกับ QuotationPolicy: ตัวธรรมดารวมกฎสถานะไว้ (หน้าเว็บใช้ซ่อน/โชว์ปุ่ม)
 * ตัวลงท้าย ...Any ตรวจแค่สิทธิ์ (API ใช้ เพื่อให้สถานะผิดตกไปเป็น 409 — ADR-014)
 */
class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SalesOrderViewAny->value);
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::SalesOrderView->value)
            && $this->owns($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SalesOrderCreate->value);
    }

    public function update(User $user, SalesOrder $order): bool
    {
        return $this->updateAny($user, $order) && $order->status->isEditable();
    }

    public function updateAny(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::SalesOrderUpdate->value)
            && $this->owns($user, $order);
    }

    /**
     * ยืนยันใบแล้วจองของ — กันของไว้จากใบอื่น จึงแยกสิทธิ์ออกจากการแก้ไข
     */
    public function confirm(User $user, SalesOrder $order): bool
    {
        return $this->confirmAny($user, $order)
            && $order->status->canTransitionTo(SalesOrderStatus::Reserved);
    }

    public function confirmAny(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::SalesOrderConfirm->value)
            && $this->owns($user, $order);
    }

    public function cancel(User $user, SalesOrder $order): bool
    {
        return $this->cancelAny($user, $order)
            && $order->status->canTransitionTo(SalesOrderStatus::Cancelled);
    }

    public function cancelAny(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::SalesOrderCancel->value)
            && $this->owns($user, $order);
    }

    public function delete(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::SalesOrderDelete->value)
            && $this->owns($user, $order)
            && $order->status->isEditable();
    }

    /**
     * เปิดใบส่งของจากใบสั่งขายนี้ได้หรือไม่
     *
     * คนคลังเป็นคนออกใบส่งของ จึงไม่ผูกกับความเป็นเจ้าของใบแบบฝ่ายขาย
     */
    public function deliver(User $user, SalesOrder $order): bool
    {
        return $user->can(PermissionName::DeliveryCreate->value) && $order->status->canDeliver();
    }

    /**
     * ใครเห็นใบนี้ได้ — sales เห็นเฉพาะของตัวเอง
     *
     * คนคลังต้องเห็นทุกใบเพื่อจัดของส่ง จึงผ่านด้วยสิทธิ์ออกใบส่งของ
     */
    private function owns(User $user, SalesOrder $order): bool
    {
        return $user->seesAllDocuments()
            || $order->sales_user_id === $user->id
            || $user->can(PermissionName::DeliveryCreate->value);
    }
}
