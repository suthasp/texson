<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * ใบสั่งขายผ่าน REST API (spec 6)
 *
 * ไม่มี store/update ของรายการ — ใบสั่งขายเกิดจากการแปลงใบเสนอราคาเท่านั้น
 * ดู POST /quotations/{id}/convert-to-so
 */
class SalesOrderController extends Controller
{
    /** ความสัมพันธ์ที่ต้องมีเสมอเวลาส่งใบรายเดียวกลับไป */
    private const DETAIL_RELATIONS = [
        'items.product:id,sku,is_serialized',
        'customer:id,code,name_th',
        'site', 'warehouse:id,code,name', 'salesUser:id,name',
        'quotation:id,quote_no,revision',
        'deliveries.warehouse:id,code,name',
    ];

    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesOrder::class);

        $orders = SalesOrder::query()
            ->with(['customer:id,code,name_th', 'warehouse:id,code,name', 'salesUser:id,name', 'items'])
            ->withCount('items')
            // sales เห็นเฉพาะใบของตัวเอง · คนคลังเห็นทุกใบเพราะเป็นคนจัดของ (spec 8)
            ->visibleTo($request->user())
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('order_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('order_date', '<=', $request->date('to')))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return SalesOrderResource::collection($orders);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('view', $salesOrder);

        return $this->resource($salesOrder);
    }

    /**
     * ยืนยันใบแล้วจองของ — ของไม่พอไม่ถือว่าผิด จองเท่าที่มีแล้วรายงานส่วนที่ขาด (spec 4.4)
     */
    public function confirm(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('confirmAny', $salesOrder);

        /** @var User $user */
        $user = $request->user();

        $order = $this->salesOrders->confirm($salesOrder, $user);

        return $this->resource($order)->additional([
            'meta' => [
                'reserved_in_full' => ! $order->load('items')->hasShortage(),
                'shortage_qty' => $order->shortageQty(),
            ],
        ]);
    }

    public function cancel(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('cancelAny', $salesOrder);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->resource($this->salesOrders->cancel($salesOrder, $data['reason'] ?? null));
    }

    private function resource(SalesOrder $order): SalesOrderResource
    {
        return new SalesOrderResource($order->load(self::DETAIL_RELATIONS));
    }
}
