<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ตัวเลขทุกตัวบนแดชบอร์ดและหน้ารายงานมาจากที่นี่ที่เดียว (spec 10 เฟส 5)
 *
 * เหตุผลที่รวมไว้คลาสเดียว: DoD ของเฟสนี้คือ "ตัวเลขตรงกับ query ตรวจมือ"
 * ถ้าปล่อยให้แต่ละหน้าจอเขียน query เอง ตัวเลขเดียวกันจะเริ่มไม่ตรงกันเมื่อมีคนแก้ที่เดียว
 *
 * นิยามที่ใช้ตลอดทั้งคลาส
 *  - "ยอดขาย"     = grand_total ของใบสั่งขายที่ไม่ถูกยกเลิก นับตาม order_date (รวม VAT)
 *  - "ส่งมอบแล้ว"  = grand_total ของใบสั่งขายที่สถานะ delivered
 *  - "win rate"   = ใบเสนอราคาที่ลูกค้าตอบรับ ÷ ใบที่ลูกค้าตัดสินใจแล้ว (ไม่นับใบที่ยังเปิดอยู่)
 *
 * ไม่คิดมูลค่าที่ส่งมอบแบบรายบรรทัดโดยเจตนา — ใบที่ส่งบางส่วนจะต้องเฉลี่ยส่วนลดท้ายบิล
 * ซึ่งได้ตัวเลขที่อธิบายให้ฝ่ายบัญชีไม่ได้ จึงนับเป็นใบทั้งใบเมื่อส่งครบเท่านั้น
 */
class ReportService
{
    /**
     * สรุปยอดขายในช่วงวันที่หนึ่ง
     *
     * @return array<string, mixed>
     */
    public function salesSummary(Carbon $from, Carbon $to, User $user): array
    {
        $orders = $this->ordersIn($from, $to, $user)
            ->get(['id', 'status', 'grand_total', 'after_discount', 'cost_total']);

        $live = $orders->reject(fn (SalesOrder $order): bool => $order->status === SalesOrderStatus::Cancelled);
        $delivered = $orders->where('status', SalesOrderStatus::Delivered);
        $cancelled = $orders->where('status', SalesOrderStatus::Cancelled);

        return [
            'order_count' => $live->count(),
            'delivered_count' => $delivered->count(),
            'cancelled_count' => $cancelled->count(),
            'ordered' => $this->sumOrders($live),
            'delivered' => $this->sumOrders($delivered),
            'cancelled' => $this->sumOrders($cancelled),
            // กำไรขั้นต้นคิดจากยอดก่อน VAT ลบต้นทุนที่ snapshot ไว้ตอนออกใบ
            'margin' => $live->reduce(
                static fn (string $carry, SalesOrder $order): string => Money::add(
                    $carry,
                    Money::sub((string) $order->after_discount, (string) $order->cost_total),
                ),
                '0.00',
            ),
        ];
    }

    /**
     * สรุปใบเสนอราคาในช่วงวันที่หนึ่ง พร้อม win rate
     *
     * @return array<string, mixed>
     */
    public function quotationSummary(Carbon $from, Carbon $to, User $user): array
    {
        $quotations = $this->quotationsIn($from, $to, $user)
            ->get(['id', 'status', 'grand_total', 'superseded_at']);

        $accepted = $quotations->where('status', QuotationStatus::Accepted);
        $rejected = $quotations->where('status', QuotationStatus::Rejected);
        $expired = $quotations->where('status', QuotationStatus::Expired);

        // ใบที่ถูกแทนที่ด้วย revision ไม่นับเป็น pipeline ซ้ำ (ADR-002)
        $open = $quotations->filter(
            static fn (Quotation $q): bool => $q->status->isOpen() && $q->superseded_at === null,
        );

        $decided = $accepted->count() + $rejected->count();

        return [
            'total' => $quotations->count(),
            'open_count' => $open->count(),
            'accepted_count' => $accepted->count(),
            'rejected_count' => $rejected->count(),
            'expired_count' => $expired->count(),
            'quoted' => $this->sumQuotations($quotations),
            'open' => $this->sumQuotations($open),
            'accepted' => $this->sumQuotations($accepted),
            // ไม่นับใบที่ยังเปิดอยู่ เพราะยังไม่รู้ผล
            'win_rate' => $decided === 0
                ? '0.00'
                : Money::percentage((string) $accepted->count(), (string) $decided),
            'decided_count' => $decided,
        ];
    }

    /**
     * ยอดขายรายเดือนย้อนหลัง — ใช้วาดแท่งเปรียบเทียบบนแดชบอร์ด
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function monthlySales(User $user, int $months = 12): Collection
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = $this->ordersIn($start, Carbon::now()->endOfMonth(), $user)
            ->get(['order_date', 'status', 'grand_total'])
            ->reject(fn (SalesOrder $order): bool => $order->status === SalesOrderStatus::Cancelled)
            ->groupBy(fn (SalesOrder $order): string => $order->order_date->format('Y-m'));

        // เติมเดือนที่ไม่มีออร์เดอร์ให้ครบ ไม่งั้นกราฟจะกระโดดข้ามเดือน
        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start, $rows): array {
                $month = $start->copy()->addMonths($offset);
                $key = $month->format('Y-m');

                return [
                    'month' => $key,
                    'label' => $month->translatedFormat('M Y'),
                    'count' => $rows->get($key)?->count() ?? 0,
                    'total' => $this->sumOrders($rows->get($key) ?? collect()),
                ];
            });
    }

    /**
     * ลูกค้าที่ซื้อมากที่สุดในช่วงวันที่หนึ่ง
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topCustomers(Carbon $from, Carbon $to, User $user, int $limit = 5): Collection
    {
        return $this->ordersIn($from, $to, $user)
            ->where('status', '!=', SalesOrderStatus::Cancelled->value)
            ->with('customer:id,code,name_th')
            ->get(['id', 'customer_id', 'grand_total'])
            ->groupBy('customer_id')
            ->map(fn (Collection $orders): array => [
                'customer' => $orders->first()->customer,
                'count' => $orders->count(),
                'total' => $this->sumOrders($orders),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['total'])
            ->take($limit)
            ->values();
    }

    /**
     * สินค้าที่ขายดีที่สุด นับจากบรรทัดของใบสั่งขายที่ไม่ถูกยกเลิก
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(Carbon $from, Carbon $to, User $user, int $limit = 5): Collection
    {
        return $this->ordersIn($from, $to, $user)
            ->where('status', '!=', SalesOrderStatus::Cancelled->value)
            ->with('items')
            ->get(['id'])
            ->flatMap(fn (SalesOrder $order) => $order->items)
            ->filter(fn ($item): bool => $item->product_id !== null)
            ->groupBy('product_id')
            ->map(fn (Collection $items): array => [
                'product_id' => $items->first()->product_id,
                'sku' => $items->first()->sku_snapshot,
                'description' => $items->first()->description,
                'qty' => $items->reduce(
                    static fn (string $carry, $item): string => bcadd($carry, (string) $item->qty_ordered, 3),
                    '0.000',
                ),
                'total' => $items->reduce(
                    static fn (string $carry, $item): string => Money::add($carry, (string) $item->line_total),
                    '0.00',
                ),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['total'])
            ->take($limit)
            ->values();
    }

    /**
     * มูลค่าสต็อกคงเหลือตามราคาทุนปัจจุบัน
     *
     * ใช้ราคาทุนใน products ไม่ใช่ต้นทุนตอนรับเข้า — ระบบยังไม่ได้ทำต้นทุนถัวเฉลี่ยรายล็อต
     * จึงเป็นตัวเลขประมาณเพื่อดูภาพรวม ไม่ใช่มูลค่าทางบัญชี
     *
     * @return array<string, mixed>
     */
    public function stockValuation(): array
    {
        $levels = StockLevel::query()
            ->with('product:id,sku,cost_price,min_stock')
            ->get();

        $value = $levels->reduce(
            static fn (string $carry, StockLevel $level): string => Money::add(
                $carry,
                Money::multiply((string) $level->qty_on_hand, (string) ($level->product?->cost_price ?? '0')),
            ),
            '0.00',
        );

        return [
            'value_at_cost' => $value,
            'sku_count' => $levels->pluck('product_id')->unique()->count(),
            'low_stock_count' => StockLevel::query()->belowMinimum()->count(),
        ];
    }

    /**
     * งานที่ต้องลงมือทำวันนี้ — ใช้บนแดชบอร์ด
     *
     * @return array<string, int>
     */
    public function actionItems(User $user): array
    {
        return [
            // ใบที่รออนุมัติจริง ๆ (ยังไม่มีใครกดอนุมัติ)
            'quotations_pending_approval' => Quotation::query()
                ->visibleTo($user)
                ->where('status', QuotationStatus::PendingApproval)
                ->whereNull('approved_at')
                ->count(),
            'quotations_expiring' => Quotation::query()->visibleTo($user)->expiringWithin(7)->count(),
            'orders_pending_confirm' => SalesOrder::query()
                ->visibleTo($user)
                ->where('status', SalesOrderStatus::Pending)
                ->count(),
            'orders_to_ship' => SalesOrder::query()
                ->visibleTo($user)
                ->whereIn('status', [SalesOrderStatus::Reserved, SalesOrderStatus::PartiallyDelivered])
                ->count(),
        ];
    }

    // ── ตัวช่วยภายใน ────────────────────────────────────────

    /**
     * ใบสั่งขายในช่วงวันที่ ที่ผู้ใช้คนนี้มีสิทธิ์เห็น
     *
     * @return Builder<SalesOrder>
     */
    private function ordersIn(Carbon $from, Carbon $to, User $user): Builder
    {
        return SalesOrder::query()
            ->visibleTo($user)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return Builder<Quotation>
     */
    private function quotationsIn(Carbon $from, Carbon $to, User $user): Builder
    {
        return Quotation::query()
            ->visibleTo($user)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @param  Collection<int, SalesOrder>  $orders
     */
    private function sumOrders(Collection $orders): string
    {
        return $orders->reduce(
            static fn (string $carry, SalesOrder $order): string => Money::add($carry, (string) $order->grand_total),
            '0.00',
        );
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     */
    private function sumQuotations(Collection $quotations): string
    {
        return $quotations->reduce(
            static fn (string $carry, Quotation $q): string => Money::add($carry, (string) $q->grand_total),
            '0.00',
        );
    }
}
