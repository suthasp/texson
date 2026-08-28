<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactLeadStatus;
use App\Enums\PermissionName;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Mail\ContactLeadReceived;
use App\Models\ContactLead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * งานของคำขอติดต่อจากหน้าเว็บสาธารณะ
 */
class ContactLeadService
{
    /**
     * บันทึกคำขอที่เพิ่งส่งเข้ามา แล้วแจ้งเตือนทีมขาย
     *
     * การส่งเมลห้ามทำให้การบันทึกล้ม — ถ้าเมลส่งไม่ออกแล้ว throw ออกไป
     * ผู้ติดต่อจะเห็นหน้า error ทั้งที่ข้อมูลเข้าฐานแล้ว และมักจะกดส่งซ้ำ
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): ContactLead
    {
        $lead = DB::transaction(fn (): ContactLead => ContactLead::create([
            ...$data,
            'status' => ContactLeadStatus::New,
        ]));

        $this->notifySales($lead);

        return $lead;
    }

    /**
     * เปลี่ยนสถานะการติดตาม
     */
    public function changeStatus(ContactLead $lead, ContactLeadStatus $target, User $actor): ContactLead
    {
        if ($lead->status === $target) {
            return $lead;
        }

        if (! $lead->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(__('คำขอติดต่อ'), $lead->status->label(), $target->label());
        }

        return DB::transaction(function () use ($lead, $target, $actor): ContactLead {
            $lead->status = $target;

            // คนแรกที่ขยับเรื่องคือเจ้าของเรื่อง ถ้ามีคนรับไปแล้วไม่แย่งกัน
            if ($lead->handled_by === null) {
                $lead->handled_by = $actor->getKey();
                $lead->handled_at = Carbon::now();
            }

            $lead->save();

            return $lead->refresh();
        });
    }

    /**
     * บันทึกภายในของทีมขาย
     */
    public function updateNote(ContactLead $lead, ?string $note): ContactLead
    {
        return DB::transaction(function () use ($lead, $note): ContactLead {
            $lead->internal_note = $note;
            $lead->save();

            return $lead->refresh();
        });
    }

    /**
     * แจ้งเตือนคนที่ตามงานคำขอได้ — ถ้าไม่มีใครเลยก็ยังบันทึกไว้ในระบบอยู่ดี
     */
    private function notifySales(ContactLead $lead): void
    {
        try {
            $recipients = User::query()
                ->where('is_active', true)
                ->permission(PermissionName::LeadViewAny->value)
                ->pluck('email');

            if ($recipients->isEmpty()) {
                return;
            }

            Mail::to($recipients->all())->send(new ContactLeadReceived($lead));
        } catch (\Throwable $e) {
            // คำขออยู่ในฐานข้อมูลแล้ว เมลที่ส่งไม่ออกไม่ควรทำให้ผู้ติดต่อเห็น error
            Log::error('ส่งอีเมลแจ้งเตือนคำขอติดต่อไม่สำเร็จ', [
                'lead_id' => $lead->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
