<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Enums\PriceTier;
use App\Enums\QuotationStatus;
use App\Enums\SettingKey;
use App\Exceptions\Domain\ApprovalRequiredException;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ตรรกะทั้งหมดของใบเสนอราคา (spec 4.2, 4.3, 4.5)
 *
 * กฎที่คลาสนี้รับผิดชอบ
 *  - ออกเลขที่ผ่าน NumberSequenceService เท่านั้น
 *  - คำนวณเงินผ่าน QuotationCalculator เท่านั้น (bcmath ทั้งหมด)
 *  - snapshot ชื่อ/ราคา/ต้นทุนของสินค้าลงในบรรทัด — แก้สินค้าภายหลังไม่กระทบใบเก่า
 *  - ใบที่ส่งแล้วห้ามแก้ทับ ต้องสร้าง revision ใหม่
 *  - ใบที่เข้าเกณฑ์ต้องผ่านการอนุมัติก่อนส่ง
 */
class QuotationService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly QuotationCalculator $calculator,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): Quotation
    {
        return DB::transaction(function () use ($data): Quotation {
            $customer = Customer::findOrFail((int) $data['customer_id']);
            $issueDate = Carbon::parse((string) ($data['issue_date'] ?? Carbon::now()->toDateString()));

            $quotation = Quotation::create([
                'quote_no' => $this->numbers->next(DocType::Quotation, $issueDate),
                'revision' => 0,
                ...$this->headerAttributes($data, $customer),
                'status' => QuotationStatus::Draft,
                'sales_user_id' => $data['sales_user_id'] ?? Auth::id(),
                'created_by' => Auth::id(),
            ]);

            $this->replaceItems($quotation, $data['items'] ?? [], (string) ($data['discount_amount'] ?? '0'));

            return $quotation->refresh()->load('items.product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Quotation $quotation, array $data): Quotation
    {
        $this->guardEditable($quotation);

        return DB::transaction(function () use ($quotation, $data): Quotation {
            $customer = Customer::findOrFail((int) $data['customer_id']);

            $quotation->update($this->headerAttributes($data, $customer));

            $this->replaceItems($quotation, $data['items'] ?? [], (string) ($data['discount_amount'] ?? '0'));

            return $quotation->refresh()->load('items.product');
        });
    }

    /**
     * ส่งเข้าคิวรออนุมัติ
     *
     * @throws InvalidStatusTransitionException
     */
    public function submit(Quotation $quotation): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::PendingApproval);

        $quotation->update([
            'status' => QuotationStatus::PendingApproval,
            // ส่งเข้าคิวใหม่ = ล้างการอนุมัติเดิม เพราะตัวเลขอาจถูกแก้หลังอนุมัติไปแล้ว
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $quotation->refresh();
    }

    /**
     * อนุมัติ — ประทับผู้อนุมัติไว้บนใบโดยไม่เปลี่ยน status (ADR-010)
     */
    public function approve(Quotation $quotation, ?User $approver = null): Quotation
    {
        if ($quotation->status !== QuotationStatus::PendingApproval) {
            throw new InvalidStatusTransitionException(
                $quotation->displayNo(),
                $quotation->status->label(),
                __('อนุมัติ'),
            );
        }

        $quotation->update([
            'approved_by' => $approver?->id ?? Auth::id(),
            'approved_at' => Carbon::now(),
        ]);

        return $quotation->refresh();
    }

    /**
     * ตีกลับให้ฝ่ายขายแก้ — กลับไปเป็นร่างและล้างการอนุมัติ
     *
     * @throws InvalidStatusTransitionException
     */
    public function returnToDraft(Quotation $quotation, ?string $reason = null): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::Draft);

        $quotation->update([
            'status' => QuotationStatus::Draft,
            'approved_by' => null,
            'approved_at' => null,
            'internal_note' => $this->appendNote($quotation->internal_note, $reason),
        ]);

        return $quotation->refresh();
    }

    /**
     * ส่งให้ลูกค้า — ตรวจเกณฑ์อนุมัติก่อนเสมอ
     *
     * @throws InvalidStatusTransitionException
     * @throws ApprovalRequiredException
     */
    public function send(Quotation $quotation): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::Sent);

        $reasons = $this->approvalReasons($quotation);

        if ($reasons !== [] && $quotation->approved_at === null) {
            throw new ApprovalRequiredException($quotation->displayNo(), $reasons);
        }

        $quotation->update([
            'status' => QuotationStatus::Sent,
            'sent_at' => Carbon::now(),
        ]);

        return $quotation->refresh();
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function accept(Quotation $quotation): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::Accepted);

        $quotation->update([
            'status' => QuotationStatus::Accepted,
            'decided_at' => Carbon::now(),
            'lost_reason' => null,
        ]);

        return $quotation->refresh();
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function reject(Quotation $quotation, ?string $lostReason = null): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::Rejected);

        $quotation->update([
            'status' => QuotationStatus::Rejected,
            'decided_at' => Carbon::now(),
            'lost_reason' => $lostReason,
        ]);

        return $quotation->refresh();
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function cancel(Quotation $quotation, ?string $reason = null): Quotation
    {
        $this->guardTransition($quotation, QuotationStatus::Cancelled);

        $quotation->update([
            'status' => QuotationStatus::Cancelled,
            'decided_at' => Carbon::now(),
            'lost_reason' => $reason,
        ]);

        return $quotation->refresh();
    }

    /**
     * สร้าง revision ใหม่จากใบที่ส่งไปแล้ว (spec 4.3)
     *
     * ใบเดิมไม่ถูกแก้และไม่เปลี่ยน status — แค่ประทับ superseded_at ไว้ (ADR-002)
     * เพื่อให้รายงานยอดเสนอราคาและ win rate ยังนับใบเดิมได้ถูกต้อง
     *
     * @throws InvalidStatusTransitionException
     */
    public function revise(Quotation $quotation): Quotation
    {
        if (! $quotation->status->canBeRevised()) {
            throw new InvalidStatusTransitionException(
                $quotation->displayNo(),
                $quotation->status->label(),
                __('สร้างฉบับแก้ไข'),
            );
        }

        return DB::transaction(function () use ($quotation): Quotation {
            $quotation->loadMissing('items');

            $validDays = $this->settings->integer(SettingKey::QuoteValidDays);

            $revision = Quotation::create([
                // เลขที่เดิม เปลี่ยนเฉพาะ revision ตามชื่อไฟล์ในสเปกข้อ 5 (ADR-009)
                'quote_no' => $quotation->quote_no,
                'revision' => $this->nextRevisionNumber($quotation->quote_no),
                'parent_quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'customer_contact_id' => $quotation->customer_contact_id,
                'customer_site_id' => $quotation->customer_site_id,
                'sales_user_id' => $quotation->sales_user_id,
                'issue_date' => Carbon::now()->toDateString(),
                'valid_until' => Carbon::now()->addDays($validDays)->toDateString(),
                'currency' => $quotation->currency,
                'price_tier' => $quotation->price_tier,
                'vat_rate' => $quotation->vat_rate,
                'status' => QuotationStatus::Draft,
                'payment_terms' => $quotation->payment_terms,
                'delivery_terms' => $quotation->delivery_terms,
                'lead_time_note' => $quotation->lead_time_note,
                'terms_and_conditions' => $quotation->terms_and_conditions,
                'customer_note' => $quotation->customer_note,
                'internal_note' => $quotation->internal_note,
                'created_by' => Auth::id(),
            ]);

            // คัดลอกบรรทัดจากใบเดิมทั้งหมด รวมทั้ง snapshot ราคาและต้นทุน
            $this->replaceItems($revision, $quotation->items->map(static fn ($item): array => [
                'product_id' => $item->product_id,
                'item_type' => $item->item_type,
                'sku_snapshot' => $item->sku_snapshot,
                'description' => $item->description,
                'uom' => $item->uom,
                'qty' => (string) $item->qty,
                'unit_price' => (string) $item->unit_price,
                'cost_snapshot' => (string) $item->cost_snapshot,
                'discount_percent' => (string) $item->discount_percent,
                'discount_amount' => (string) $item->discount_amount,
                'lead_time_days' => $item->lead_time_days,
            ])->all(), (string) $quotation->discount_amount);

            $quotation->update(['superseded_at' => Carbon::now()]);

            return $revision->refresh()->load('items.product');
        });
    }

    /**
     * เปลี่ยนใบที่เลยวันยืนราคาเป็น expired — เรียกจาก command ที่รันทุกเช้า 06:00
     *
     * @return int จำนวนใบที่ถูกเปลี่ยน
     */
    public function expireOverdue(?Carbon $asOf = null): int
    {
        $today = ($asOf ?? Carbon::now())->startOfDay();

        $overdue = Quotation::query()
            ->where('status', QuotationStatus::Sent->value)
            ->whereDate('valid_until', '<', $today->toDateString())
            ->get();

        foreach ($overdue as $quotation) {
            // อัปเดตทีละใบเพื่อให้ activity log บันทึกค่าก่อน/หลังครบทุกใบ (spec 8)
            $quotation->update(['status' => QuotationStatus::Expired]);
        }

        return $overdue->count();
    }

    /**
     * ใบนี้เข้าเกณฑ์ต้องอนุมัติหรือไม่ พร้อมเหตุผล (spec 4.3)
     *
     * @return array<int, string> ว่าง = ส่งได้เลย
     */
    public function approvalReasons(Quotation $quotation): array
    {
        $quotation->loadMissing('items');

        $maxDiscount = $this->settings->decimal(SettingKey::ApprovalMaxDiscountPercent);
        $minMargin = $this->settings->decimal(SettingKey::ApprovalMinMarginPercent);
        $maxTotal = $this->settings->decimal(SettingKey::ApprovalMaxGrandTotal);

        $reasons = [];

        $discountPercent = $quotation->totalDiscountPercent();

        if (Money::greaterThan($discountPercent, $maxDiscount)) {
            $reasons[] = __('ส่วนลดรวม :actual% เกิน :limit%', [
                'actual' => Money::format($discountPercent),
                'limit' => Money::format($maxDiscount),
            ]);
        }

        // ใบที่ยังไม่มีต้นทุนเลย (เช่น มีแต่บรรทัดข้อความ) ไม่ควรติดเกณฑ์ margin
        if (! Money::isZero((string) $quotation->cost_total)
            && Money::lessThan($quotation->marginPercent(), $minMargin)) {
            $reasons[] = __('margin :actual% ต่ำกว่า :limit%', [
                'actual' => Money::format($quotation->marginPercent()),
                'limit' => Money::format($minMargin),
            ]);
        }

        if (Money::greaterThan((string) $quotation->grand_total, $maxTotal)) {
            $reasons[] = __('ยอดสุทธิ :actual เกิน :limit บาท', [
                'actual' => Money::format((string) $quotation->grand_total),
                'limit' => Money::format($maxTotal),
            ]);
        }

        return $reasons;
    }

    public function requiresApproval(Quotation $quotation): bool
    {
        return $this->approvalReasons($quotation) !== [];
    }

    /**
     * ค่าเริ่มต้นสำหรับใบใหม่ ดึงจากค่าตั้งระบบและระดับราคาของลูกค้า
     *
     * @return array<string, mixed>
     */
    public function defaultsFor(?Customer $customer = null): array
    {
        $validDays = $this->settings->integer(SettingKey::QuoteValidDays);

        return [
            'issue_date' => Carbon::now()->toDateString(),
            'valid_until' => Carbon::now()->addDays($validDays)->toDateString(),
            'vat_rate' => $this->settings->decimal(SettingKey::VatRate),
            'price_tier' => $customer?->price_tier ?? PriceTier::Standard,
            'payment_terms' => $customer?->payment_terms ?: $this->settings->string(SettingKey::PaymentTerms),
            'delivery_terms' => $this->settings->string(SettingKey::DeliveryTerms),
            'lead_time_note' => $this->settings->string(SettingKey::LeadTimeNote),
            'terms_and_conditions' => $this->settings->string(SettingKey::TermsAndConditionsTh),
        ];
    }

    // ── ภายใน ───────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerAttributes(array $data, Customer $customer): array
    {
        $tier = isset($data['price_tier'])
            ? PriceTier::from((string) $data['price_tier'])
            : $customer->price_tier;

        return [
            'customer_id' => $customer->id,
            'customer_contact_id' => $data['customer_contact_id'] ?? null,
            'customer_site_id' => $data['customer_site_id'] ?? null,
            'issue_date' => $data['issue_date'],
            'valid_until' => $data['valid_until'],
            'currency' => $data['currency'] ?? 'THB',
            'price_tier' => $tier,
            'vat_rate' => Money::normalize($data['vat_rate'] ?? $this->settings->decimal(SettingKey::VatRate)),
            'payment_terms' => $data['payment_terms'] ?? null,
            'delivery_terms' => $data['delivery_terms'] ?? null,
            'lead_time_note' => $data['lead_time_note'] ?? null,
            'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
        ];
    }

    /**
     * เขียนบรรทัดใหม่ทั้งชุดแล้วคำนวณยอดท้ายบิลใหม่
     *
     * ลบทิ้งแล้วสร้างใหม่แทนการ diff เพราะบรรทัดไม่มีความหมายข้ามการแก้ไข
     * (ไม่มีอะไรอ้างถึง quotation_items.id จนกว่าจะแปลงเป็นใบสั่งขายใน Phase 4)
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(Quotation $quotation, array $items, string $headerDiscount): void
    {
        $result = $this->calculator->calculate(
            $this->hydrateSnapshots($items, $quotation->price_tier),
            $headerDiscount,
            (string) $quotation->vat_rate,
        );

        $quotation->items()->delete();

        foreach ($result['lines'] as $line) {
            $quotation->items()->create($line);
        }

        $totals = $result['totals'];

        $quotation->update([
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount_amount'],
            'after_discount' => $totals['after_discount'],
            'vat_amount' => $totals['vat_amount'],
            'grand_total' => $totals['grand_total'],
            'cost_total' => $totals['cost_total'],
        ]);
    }

    /**
     * เติม snapshot ที่ฟอร์มไม่ได้ส่งมา จากสินค้าจริงในระบบ
     *
     * ราคาที่ sales พิมพ์ทับมาถือเป็นราคาที่ใช้จริง (spec 4.5) — เราไม่เขียนทับ
     * แต่ต้นทุนต้องมาจากฐานข้อมูลเสมอ ไม่รับจากฟอร์ม เพราะ margin จะถูกปลอมได้
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function hydrateSnapshots(array $items, PriceTier $tier): array
    {
        $productIds = array_values(array_filter(array_map(
            static fn (array $item): ?int => isset($item['product_id']) && $item['product_id'] !== ''
                ? (int) $item['product_id']
                : null,
            $items,
        )));

        $products = $productIds === []
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return array_values(array_map(static function (array $item) use ($products, $tier): array {
            $product = isset($item['product_id']) && $item['product_id'] !== ''
                ? $products->get((int) $item['product_id'])
                : null;

            if ($product instanceof Product) {
                $item['sku_snapshot'] ??= $product->sku;
                $item['uom'] = $item['uom'] ?? $product->uom->label();

                if (! isset($item['description']) || trim((string) $item['description']) === '') {
                    $item['description'] = $product->displayName();
                }

                if (! isset($item['unit_price']) || $item['unit_price'] === '') {
                    $item['unit_price'] = $product->priceFor($tier);
                }

                // ต้นทุนมาจากฐานข้อมูลเท่านั้น
                $item['cost_snapshot'] = (string) $product->cost_price;
                $item['lead_time_days'] ??= $product->lead_time_days;
            } else {
                $item['cost_snapshot'] = $item['cost_snapshot'] ?? '0';
            }

            return $item;
        }, $items));
    }

    /**
     * revision ถัดไปของสายเลขที่นี้ — อ่านจากค่าสูงสุดที่มีอยู่จริง
     * (ใบเดิมอาจถูกแก้ซ้ำได้ ถ้าเผลอสร้าง revision จากใบกลางสาย)
     */
    private function nextRevisionNumber(string $quoteNo): int
    {
        return (int) Quotation::withTrashed()
            ->where('quote_no', $quoteNo)
            ->max('revision') + 1;
    }

    private function appendNote(?string $existing, ?string $addition): ?string
    {
        if (blank($addition)) {
            return $existing;
        }

        $stamp = __('[ตีกลับ :at] :reason', [
            'at' => Carbon::now()->format('Y-m-d H:i'),
            'reason' => $addition,
        ]);

        return blank($existing) ? $stamp : $existing."\n".$stamp;
    }

    private function guardEditable(Quotation $quotation): void
    {
        if (! $quotation->status->isEditable()) {
            throw new InvalidStatusTransitionException(
                $quotation->displayNo(),
                $quotation->status->label(),
                __('แก้ไข'),
            );
        }
    }

    private function guardTransition(Quotation $quotation, QuotationStatus $target): void
    {
        if (! $quotation->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $quotation->displayNo(),
                $quotation->status->label(),
                $target->label(),
            );
        }
    }
}
