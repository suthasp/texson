<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports/low-stock (spec 6)
     */
    public function lowStock(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        $levels = StockLevel::query()
            ->belowMinimum()
            ->with(['product:id,sku,name_th,min_stock,reorder_qty', 'warehouse:id,code,name'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('stock_levels.warehouse_id', $request->integer('warehouse_id')))
            ->orderBy('products.sku')
            ->paginate(min($request->integer('per_page', 50), 200))
            ->withQueryString();

        return StockLevelResource::collection($levels)->additional([
            'meta' => [
                'as_of' => Carbon::now()->toIso8601String(),
                // จำนวนที่ควรสั่งเพิ่มต่อรายการอยู่ใน products.reorder_qty
                'basis' => 'qty_available < products.min_stock',
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/sales-summary?from=&to= (spec 6)
     *
     * มีสองฐาน (ADR-015)
     *  - quotations   = pipeline และ win rate จากใบเสนอราคา
     *  - sales_orders = ยอดที่ปิดการขายจริงและความคืบหน้าการส่งของ (เพิ่มใน Phase 4)
     *
     * ฟิลด์เดิมของฐาน quotations ไม่ถูกแก้ความหมาย — ฐานใหม่เพิ่มเข้ามาเป็นบล็อกของตัวเอง
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'] ?? Carbon::now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? Carbon::now()->toDateString())->endOfDay();

        $quotations = Quotation::query()
            ->visibleTo($request->user())
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'status', 'grand_total', 'cost_total', 'after_discount', 'superseded_at']);

        $sum = static fn ($collection): string => $collection->reduce(
            static fn (string $carry, Quotation $q): string => Money::add($carry, (string) $q->grand_total),
            '0.00',
        );

        $accepted = $quotations->where('status', QuotationStatus::Accepted);
        $rejected = $quotations->where('status', QuotationStatus::Rejected);
        $expired = $quotations->where('status', QuotationStatus::Expired);
        $open = $quotations->filter(static fn (Quotation $q): bool => $q->status->isOpen() && $q->superseded_at === null);

        // win rate นับเฉพาะใบที่ลูกค้าตัดสินใจแล้ว ใบที่ยังเปิดอยู่ยังไม่รู้ผล
        $decidedCount = $accepted->count() + $rejected->count();

        $acceptedMargin = $accepted->reduce(
            static fn (string $carry, Quotation $q): string => Money::add(
                $carry,
                Money::sub((string) $q->after_discount, (string) $q->cost_total),
            ),
            '0.00',
        );

        $orders = SalesOrder::query()
            ->visibleTo($request->user())
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->with('items')
            ->get();

        $liveOrders = $orders->filter(
            static fn (SalesOrder $order): bool => $order->status !== SalesOrderStatus::Cancelled,
        );

        $orderSum = static fn ($collection): string => $collection->reduce(
            static fn (string $carry, SalesOrder $order): string => Money::add($carry, (string) $order->grand_total),
            '0.00',
        );

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'quotations' => [
                    'total' => $quotations->count(),
                    'open' => $open->count(),
                    'accepted' => $accepted->count(),
                    'rejected' => $rejected->count(),
                    'expired' => $expired->count(),
                ],
                'amounts' => [
                    'quoted' => $sum($quotations),
                    'open' => $sum($open),
                    'accepted' => $sum($accepted),
                    'rejected' => $sum($rejected),
                    'accepted_margin' => $acceptedMargin,
                ],
                'win_rate_percent' => $decidedCount === 0
                    ? '0.00'
                    : Money::percentage((string) $accepted->count(), (string) $decidedCount),

                // ── ฐานใบสั่งขาย: ยอดที่ปิดการขายจริง (เพิ่มใน Phase 4) ──
                'sales_orders' => [
                    'total' => $orders->count(),
                    'open' => $liveOrders->filter(static fn (SalesOrder $o): bool => ! $o->status->isClosed())->count(),
                    'delivered' => $orders->where('status', SalesOrderStatus::Delivered)->count(),
                    'cancelled' => $orders->where('status', SalesOrderStatus::Cancelled)->count(),
                    // ใบที่จองของไม่ครบ — ต้องสั่งของเพิ่มถึงจะส่งจบ (spec 4.4)
                    'with_shortage' => $liveOrders->filter(static fn (SalesOrder $o): bool => $o->hasShortage())->count(),
                    'amounts' => [
                        'ordered' => $orderSum($liveOrders),
                        'delivered' => $orderSum($orders->where('status', SalesOrderStatus::Delivered)),
                        'cancelled' => $orderSum($orders->where('status', SalesOrderStatus::Cancelled)),
                    ],
                ],
            ],
            'meta' => [
                'basis' => 'quotations',
                // ฐานที่ report นี้ครอบคลุม — ฟิลด์ระดับบนสุดยังคิดจากใบเสนอราคาเหมือนเดิม
                'includes' => ['quotations', 'sales_orders'],
                'currency' => 'THB',
                'amounts_include_vat' => true,
                'win_rate_excludes_open' => true,
                'sales_order_amounts_exclude_cancelled' => true,
            ],
        ]);
    }
}
