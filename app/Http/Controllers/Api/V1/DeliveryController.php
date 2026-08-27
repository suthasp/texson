<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * ใบส่งของผ่าน REST API (spec 6)
 *
 * POST /sales-orders/{id}/deliveries  → สร้างใบร่าง
 * POST /deliveries/{id}/post          → ตัดสต็อกจริง
 */
class DeliveryController extends Controller
{
    private const DETAIL_RELATIONS = [
        'items.salesOrderItem', 'items.product:id,sku',
        'salesOrder:id,so_no', 'warehouse:id,code,name', 'poster:id,name',
        'movements.product:id,sku', 'movements.warehouse:id,code',
    ];

    public function __construct(private readonly DeliveryService $deliveries) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = Delivery::query()
            ->with(['salesOrder:id,so_no', 'warehouse:id,code,name'])
            ->withCount('items')
            ->visibleTo($request->user())
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('sales_order_id'), fn ($q) => $q->where('sales_order_id', $request->integer('sales_order_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('delivery_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('delivery_date', '<=', $request->date('to')))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return DeliveryResource::collection($deliveries);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        $this->authorize('view', $delivery);

        return $this->resource($delivery);
    }

    /**
     * POST /api/v1/sales-orders/{id}/deliveries
     *
     * ใบสั่งขายที่ยังไม่ยืนยันจะได้ 409 จาก service ไม่ใช่ 403
     */
    public function store(DeliveryRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        $delivery = $this->deliveries->createDraft($salesOrder, $request->validated());

        return $this->resource($delivery)
            ->response()
            ->setStatusCode(201);
    }

    public function update(DeliveryRequest $request, Delivery $delivery): DeliveryResource
    {
        return $this->resource($this->deliveries->updateDraft($delivery, $request->validated()));
    }

    /**
     * POST /api/v1/deliveries/{id}/post — ตัดสต็อกจริง ย้อนกลับไม่ได้
     */
    public function post(Delivery $delivery): DeliveryResource
    {
        $this->authorize('postAny', $delivery);

        $posted = $this->deliveries->post($delivery);

        return $this->resource($posted)->additional([
            'meta' => [
                'sales_order_status' => $posted->salesOrder()->value('status'),
            ],
        ]);
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        $this->authorize('delete', $delivery);

        $this->deliveries->cancel($delivery);

        return response()->json(['data' => ['cancelled' => true], 'meta' => []]);
    }

    /**
     * GET /api/v1/sales-orders/{id}/outstanding — ของที่ยังค้างส่ง
     *
     * ไม่ได้อยู่ในรายการของสเปกข้อ 6 แต่จำเป็นคู่กับการสร้างใบส่งของ
     * เพราะผู้เรียกต้องรู้ว่าเหลืออะไรให้ส่งบ้างก่อนจะยิง POST
     */
    public function outstanding(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        return response()->json([
            'data' => $this->deliveries->outstandingLines($salesOrder),
            'meta' => [
                'sales_order_no' => $salesOrder->so_no,
                'status' => $salesOrder->status->value,
                'can_deliver' => $salesOrder->status->canDeliver(),
            ],
        ]);
    }

    private function resource(Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($delivery->load(self::DETAIL_RELATIONS));
    }
}
