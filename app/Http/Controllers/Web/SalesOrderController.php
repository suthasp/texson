<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesOrderRequest;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesOrder::class);

        $orders = SalesOrder::query()
            ->with(['customer:id,code,name_th', 'warehouse:id,code', 'salesUser:id,name'])
            ->withCount('items')
            // sales เห็นเฉพาะใบของตัวเอง — กรองที่ query ไม่ใช่ที่ view (spec 8)
            ->visibleTo($request->user())
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales-orders.index', [
            'orders' => $orders,
            'statuses' => SalesOrderStatus::options(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'filters' => $request->only(['q', 'status', 'warehouse_id', 'open']),
        ]);
    }

    public function show(SalesOrder $salesOrder): View
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load([
            'items.product.stockLevels',
            'customer', 'site', 'warehouse', 'salesUser', 'creator', 'confirmer',
            'quotation:id,quote_no,revision',
            'deliveries.items', 'deliveries.warehouse:id,code',
            'serialNumbers.product:id,sku',
        ]);

        return view('sales-orders.show', ['order' => $salesOrder]);
    }

    public function edit(SalesOrder $salesOrder): View
    {
        $this->authorize('update', $salesOrder);

        $salesOrder->load('items', 'customer.sites');

        return view('sales-orders.edit', [
            'order' => $salesOrder,
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('customer_po_file')) {
            $previous = $salesOrder->customer_po_file;

            // ตั้งชื่อสุ่มและเก็บใน storage/app/private (spec 8)
            $data['customer_po_file'] = $request->file('customer_po_file')->store('customer-po', 'private');

            if (filled($previous) && Storage::disk('private')->exists($previous)) {
                Storage::disk('private')->delete($previous);
            }
        } else {
            unset($data['customer_po_file']);
        }

        $salesOrder->update($data);

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with('success', __('บันทึกใบสั่งขาย :no แล้ว', ['no' => $salesOrder->so_no]));
    }

    /**
     * ยืนยันใบแล้วจองของในคลัง (spec 4.4)
     */
    public function confirm(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('confirm', $salesOrder);

        /** @var User $user */
        $user = $request->user();

        $order = $this->salesOrders->confirm($salesOrder, $user);

        $message = $order->hasShortage()
            ? __('ยืนยันใบ :no แล้ว — จองของได้ไม่ครบ ขาดอีก :qty ชิ้น บันทึกเป็น backorder ไว้', [
                'no' => $order->so_no,
                'qty' => rtrim(rtrim($order->shortageQty(), '0'), '.'),
            ])
            : __('ยืนยันใบ :no และจองของครบแล้ว', ['no' => $order->so_no]);

        return back()->with($order->hasShortage() ? 'warning' : 'success', $message);
    }

    public function cancel(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->authorize('cancel', $salesOrder);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->salesOrders->cancel($salesOrder, $data['reason'] ?? null);

        return back()->with('success', __('ยกเลิกใบสั่งขาย :no และคืนของที่จองไว้แล้ว', ['no' => $salesOrder->so_no]));
    }

    /**
     * ส่งไฟล์ใบสั่งซื้อของลูกค้าผ่าน controller ที่ตรวจสิทธิ์ ไม่เปิด symlink ออก public (spec 8)
     */
    public function purchaseOrderFile(SalesOrder $salesOrder): StreamedResponse
    {
        $this->authorize('view', $salesOrder);

        abort_if(
            blank($salesOrder->customer_po_file) || ! Storage::disk('private')->exists($salesOrder->customer_po_file),
            404,
        );

        return Storage::disk('private')->response($salesOrder->customer_po_file);
    }
}
