<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * งานตาม PDPA ของข้อมูลลูกค้าและผู้ติดต่อ (spec 8)
 *
 * รวมสามเรื่องไว้ที่เดียว: บันทึกการเข้าถึง · รวบรวมข้อมูลที่ระบบเก็บไว้เพื่อส่งให้เจ้าของข้อมูล ·
 * ลบข้อมูลส่วนบุคคลตามคำขอโดยยังเก็บเอกสารทางบัญชี
 */
class PersonalDataService
{
    /** ข้อความที่ใส่แทนข้อมูลจริงหลังลบตามคำขอ */
    public const ERASED = '[ลบตามคำขอ PDPA]';

    /** log name แยกจาก 'default' เพื่อให้กรองดูเฉพาะเรื่อง PDPA ได้ */
    public const LOG = 'pdpa';

    /**
     * บันทึกว่ามีคนเปิดดูข้อมูลส่วนบุคคลของลูกค้ารายนี้
     *
     * ยุบเป็นวันละครั้งต่อคนต่อลูกค้า — คำถามที่ต้องตอบได้คือ "ใครเข้าถึงข้อมูลของใครวันไหน"
     * ถ้า log ทุกครั้งที่กด refresh ตารางจะโตจนหาอะไรไม่เจอ โดยที่คำตอบไม่ได้ดีขึ้นเลย (ADR-026)
     */
    public function logAccess(Customer $customer, User $user): void
    {
        $alreadyLoggedToday = Activity::query()
            ->where('log_name', self::LOG)
            ->where('event', 'accessed')
            ->where('subject_type', $customer->getMorphClass())
            ->where('subject_id', $customer->getKey())
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->getKey())
            ->where('created_at', '>=', Carbon::today())
            ->exists();

        if ($alreadyLoggedToday) {
            return;
        }

        $this->write($customer, $user, 'accessed', __('เปิดดูข้อมูลผู้ติดต่อของลูกค้า'));
    }

    /**
     * การส่งออกข้อมูลรายคนคือการนำข้อมูลออกนอกระบบ จึงบันทึกทุกครั้ง ไม่ยุบเหมือนการเปิดดู
     */
    public function logExport(Customer $customer, User $user): void
    {
        $this->write($customer, $user, 'exported', __('ส่งออกข้อมูลส่วนบุคคลของลูกค้า'));
    }

    /**
     * ข้อมูลทั้งหมดที่ระบบเก็บเกี่ยวกับลูกค้ารายนี้ สำหรับส่งให้เจ้าของข้อมูลตามสิทธิ์ขอเข้าถึง
     *
     * เอกสารใส่แค่หัวใบ ไม่ลงรายการสินค้าและราคา เพราะสิทธิ์ตาม PDPA คือขอ "ข้อมูลส่วนบุคคล
     * ที่ผู้ควบคุมเก็บไว้" ไม่ใช่สำเนาเอกสารการค้าทั้งหมด
     *
     * @return array<string, mixed>
     */
    public function export(Customer $customer): array
    {
        $customer->load(['contacts', 'sites.primaryContact']);

        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'customer' => [
                'code' => $customer->code,
                'name_th' => $customer->name_th,
                'name_en' => $customer->name_en,
                'tax_id' => $customer->tax_id,
                'branch_code' => $customer->branch_code,
                'address' => $customer->fullAddress(),
                'phone' => $customer->phone,
                'email' => $customer->email,
                'price_tier' => $customer->price_tier->value,
                'credit_term_days' => $customer->credit_term_days,
                'payment_terms' => $customer->payment_terms,
                'notes' => $customer->notes,
                'is_active' => $customer->is_active,
                'created_at' => $customer->created_at?->toIso8601String(),
                'updated_at' => $customer->updated_at?->toIso8601String(),
                'deleted_at' => $customer->deleted_at?->toIso8601String(),
                'anonymized_at' => $customer->anonymized_at?->toIso8601String(),
            ],
            'contacts' => $customer->contacts->map(fn (object $contact): array => [
                'name' => $contact->name,
                'position' => $contact->position,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'line_id' => $contact->line_id,
                'is_primary' => $contact->is_primary,
            ])->all(),
            'sites' => $customer->sites->map(fn (object $site): array => [
                'site_code' => $site->site_code,
                'site_name' => $site->site_name,
                'address_line' => $site->address_line,
                'province' => $site->province,
                'access_note' => $site->access_note,
                'primary_contact' => $site->primaryContact?->name,
            ])->all(),
            'documents' => [
                'quotations' => $customer->quotations()
                    ->orderBy('issue_date')
                    ->get()
                    ->map(fn (object $quotation): array => [
                        'quote_no' => $quotation->quote_no,
                        'revision' => $quotation->revision,
                        'issue_date' => $quotation->issue_date?->toDateString(),
                        'status' => $quotation->status->value,
                        'grand_total' => $quotation->grand_total,
                    ])->all(),
                'sales_orders' => $customer->salesOrders()
                    ->orderBy('order_date')
                    ->get()
                    ->map(fn (object $order): array => [
                        'so_no' => $order->so_no,
                        'order_date' => $order->order_date?->toDateString(),
                        'status' => $order->status->value,
                        'grand_total' => $order->grand_total,
                    ])->all(),
            ],
            'access_log' => $this->accessLog($customer)
                ->map(fn (Activity $activity): array => [
                    'at' => $activity->created_at?->toIso8601String(),
                    'event' => $activity->event,
                    'by' => $activity->causer?->name,
                    'description' => $activity->description,
                ])->all(),
        ];
    }

    /**
     * ประวัติการเข้าถึง / ส่งออก / ลบ ข้อมูลส่วนบุคคลของลูกค้ารายนี้
     *
     * @return Collection<int, Activity>
     */
    public function accessLog(Customer $customer, int $limit = 100): Collection
    {
        return Activity::query()
            ->where('log_name', self::LOG)
            ->where('subject_type', $customer->getMorphClass())
            ->where('subject_id', $customer->getKey())
            ->with('causer')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * ลบข้อมูลส่วนบุคคลตามคำขอ โดยเก็บตัวลูกค้าและเอกสารไว้ (ADR-024)
     *
     * ฟิลด์ที่เป็นข้อมูลส่วนบุคคลถูกล้าง ผู้ติดต่อถูกลบทั้งหมด ส่วน code / ยอดเงิน /
     * เลขที่เอกสาร ยังอยู่ครบเพื่อให้งบการเงินและรายงานภาษีย้อนหลังยังตรง
     *
     * ทำแล้วย้อนไม่ได้ ข้อมูลเดิมไม่ถูกเก็บไว้ที่ไหนอีก — activity log บันทึกแค่ว่า
     * มีอะไรถูกลบไปกี่รายการ ไม่ได้เก็บค่าที่ลบ ไม่งั้นก็เท่ากับไม่ได้ลบ
     */
    public function anonymize(Customer $customer, User $actor, ?string $reason = null): Customer
    {
        return DB::transaction(function () use ($customer, $actor, $reason): Customer {
            $erased = [
                'reason' => $reason,
                'contacts' => $customer->contacts()->count(),
                'sites' => $customer->sites()->count(),
                'had_tax_id' => filled($customer->tax_id),
                'had_phone' => filled($customer->phone),
                'had_email' => filled($customer->email),
            ];

            $customer->contacts()->delete();

            // access_note ของ site เป็นวิธีเข้าพื้นที่ ไม่ใช่ข้อมูลบุคคลโดยตรง
            // แต่ในทางปฏิบัติมักมีชื่อ รปภ. หรือผู้ดูแลอาคารปนอยู่ จึงล้างไปด้วย
            $customer->sites()->update(['access_note' => null, 'primary_contact_id' => null]);

            $customer->forceFill([
                'name_th' => self::ERASED,
                'name_en' => null,
                'tax_id' => null,
                'address_line' => null,
                'subdistrict' => null,
                'district' => null,
                'province' => null,
                'postcode' => null,
                'phone' => null,
                'email' => null,
                'notes' => null,
                'is_active' => false,
                'anonymized_at' => Carbon::now(),
                'anonymized_by' => $actor->getKey(),
            ])->save();

            if (! $customer->trashed()) {
                $customer->delete();
            }

            $this->write($customer, $actor, 'anonymized', __('ลบข้อมูลส่วนบุคคลตามคำขอ PDPA'), $erased);

            return $customer->refresh();
        });
    }

    /**
     * ลูกค้าที่ยังไม่เคยมีเอกสารเลย ลบทิ้งทั้งแถวได้จริง ไม่ต้องเก็บอะไรไว้
     */
    public function canBeDeletedOutright(Customer $customer): bool
    {
        return $customer->quotations()->withTrashed()->doesntExist()
            && $customer->salesOrders()->doesntExist();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function write(Customer $customer, User $user, string $event, string $description, array $properties = []): void
    {
        activity(self::LOG)
            ->performedOn($customer)
            ->causedBy($user)
            ->event($event)
            ->withProperties([
                'customer_code' => $customer->code,
                'ip' => request()->ip(),
                ...$properties,
            ])
            ->log($description);
    }
}
