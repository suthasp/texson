<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryRequest;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Delivery::class);

        $deliveries = Delivery::query()
            ->with(['salesOrder:id,so_no,customer_id', 'salesOrder.customer:id,name_th', 'warehouse:id,code', 'creator:id,name'])
            ->withCount('items')
            ->visibleTo($request->user())
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'statuses' => StockDocumentStatus::options(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'filters' => $request->only(['q', 'status', 'warehouse_id']),
        ]);
    }

    /**
     * เปิดใบส่งของจากใบสั่งขาย — บรรทัดตั้งต้นคือของที่ยังค้างส่ง
     */
    public function create(SalesOrder $salesOrder): View
    {
        $this->authorize('create', Delivery::class);
        $this->authorize('deliver', $salesOrder);

        $salesOrder->load('items.product', 'customer', 'warehouse');

        return view('deliveries.create', [
            'order' => $salesOrder,
            'delivery' => new Delivery([
                'warehouse_id' => $salesOrder->warehouse_id,
                'delivery_date' => now(),
            ]),
            'lines' => $this->deliveries->outstandingLines($salesOrder),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function store(DeliveryRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $delivery = $this->deliveries->createDraft($salesOrder, $request->validated());

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('success', __('สร้างใบส่งของ :no แล้ว — ยังเป็นร่าง ยังไม่ตัดสต็อก', ['no' => $delivery->delivery_no]));
    }

    public function show(Delivery $delivery): View
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'items.product', 'items.salesOrderItem',
            'salesOrder.customer', 'salesOrder.site',
            'warehouse', 'creator', 'poster',
            'movements.product:id,sku',
        ]);

        return view('deliveries.show', ['delivery' => $delivery]);
    }

    public function edit(Delivery $delivery): View
    {
        $this->authorize('update', $delivery);

        $delivery->load('items.product', 'items.salesOrderItem', 'salesOrder.items.product', 'salesOrder.customer');

        return view('deliveries.edit', [
            'delivery' => $delivery,
            'order' => $delivery->salesOrder,
            'lines' => $this->existingLines($delivery),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function update(DeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->deliveries->updateDraft($delivery, $request->validated());

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('success', __('แก้ไขใบส่งของ :no แล้ว', ['no' => $delivery->delivery_no]));
    }

    /**
     * ตัดสต็อกจริง — ย้อนกลับไม่ได้
     */
    public function post(Delivery $delivery): RedirectResponse
    {
        $this->authorize('post', $delivery);

        $this->deliveries->post($delivery);

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('success', __('บันทึกใบ :no แล้ว — ตัดสต็อกและปิดยอดจองเรียบร้อย', ['no' => $delivery->delivery_no]));
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        $this->authorize('delete', $delivery);

        $this->deliveries->cancel($delivery);

        return redirect()
            ->route('sales-orders.show', $delivery->sales_order_id)
            ->with('success', __('ยกเลิกใบส่งของ :no แล้ว', ['no' => $delivery->delivery_no]));
    }

    /**
     * บรรทัดของใบที่มีอยู่แล้ว รวมกับของที่ยังค้างส่งบรรทัดอื่น
     *
     * @return array<int, array<string, mixed>>
     */
    private function existingLines(Delivery $delivery): array
    {
        return $delivery->items
            ->map(function ($item): array {
                $orderItem = $item->salesOrderItem;

                return [
                    'sales_order_item_id' => $orderItem->id,
                    'product_id' => $item->product_id,
                    'sku' => $orderItem->sku_snapshot,
                    'description' => $orderItem->description,
                    'uom' => $orderItem->uom,
                    'is_serialized' => (bool) $item->product?->is_serialized,
                    'qty_ordered' => (string) $orderItem->qty_ordered,
                    'qty_delivered' => (string) $orderItem->qty_delivered,
                    'qty' => (string) $item->qty,
                    'serial_numbers' => implode("\n", $item->serials()),
                    'lot_no' => $item->lot_no,
                ];
            })
            ->values()
            ->all();
    }
}
