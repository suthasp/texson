<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Models\Quotation;
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
     * ตอนนี้คิดจาก "ใบเสนอราคา" เพราะตาราง sales_orders ยังไม่มี (อยู่ใน Phase 4)
     * meta.basis บอกฐานที่ใช้คำนวณไว้ชัดเจน เมื่อ Phase 4 เสร็จจะเพิ่มชุดตัวเลขจาก
     * ใบสั่งขายเข้ามาเป็นฟิลด์ใหม่ ไม่แก้ความหมายของฟิลด์เดิม
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
            ],
            'meta' => [
                // Phase 4 จะเพิ่มฐานจากใบสั่งขายเข้ามาโดยไม่แก้ความหมายของฟิลด์ที่มีอยู่
                'basis' => 'quotations',
                'currency' => 'THB',
                'amounts_include_vat' => true,
                'win_rate_excludes_open' => true,
            ],
        ]);
    }
}
