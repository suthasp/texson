<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StockAdjustRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $adjustments) {}

    /**
     * POST /api/v1/stock/adjust — role: warehouse|admin (spec 6)
     *
     * สร้างใบปรับปรุงแล้ว post ให้ในครั้งเดียว (ตั้ง post=false ถ้าอยากเก็บเป็นร่างไว้ก่อน)
     * ทุกอย่างผ่าน StockAdjustmentService จึงเขียน ledger ครบเหมือนกดจากหน้าเว็บ
     */
    public function adjust(StockAdjustRequest $request): StockAdjustmentResource
    {
        $adjustment = $this->adjustments->createDraft($request->validated());

        if ($request->shouldPost()) {
            // สิทธิ์ post แยกจากสิทธิ์สร้างใบ เพราะเป็นการกระทำที่ย้อนกลับไม่ได้
            $this->authorize('post', $adjustment);

            $adjustment = $this->adjustments->post($adjustment);
        }

        return (new StockAdjustmentResource(
            $adjustment->load(['items.product:id,sku', 'warehouse:id,code,name', 'movements.product:id,sku'])
        ))->additional([
            'meta' => ['posted' => $request->shouldPost()],
        ]);
    }

    /**
     * GET /api/v1/stock/ledger — ประวัติการเคลื่อนไหว
     *
     * ไม่ได้อยู่ในรายการของสเปกข้อ 6 แต่จำเป็นคู่กับ /stock/adjust
     * เพราะผู้เรียกต้องตรวจได้ว่าการปรับที่ยิงไปกลายเป็นรายการอะไรใน ledger
     */
    public function ledger(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewLedger', StockLevel::class);

        $movements = StockMovement::query()
            ->with(['product:id,sku,name_th', 'warehouse:id,code', 'user:id,name'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('moved_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('moved_at', '<=', $request->date('to')))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 50), 200))
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }
}
