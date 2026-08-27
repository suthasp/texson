<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Concerns\ProvidesProductOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    use ProvidesProductOptions;

    public function __construct(private readonly GoodsReceiptService $receipts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $receipts = GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'creator'])
            ->withCount('items')
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('goods-receipts.index', [
            'receipts' => $receipts,
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'statuses' => StockDocumentStatus::options(),
            'filters' => $request->only(['q', 'status', 'warehouse_id']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GoodsReceipt::class);

        return view('goods-receipts.create', [
            'receipt' => new GoodsReceipt(['received_date' => now()]),
            ...$this->formData(),
        ]);
    }

    public function store(GoodsReceiptRequest $request): RedirectResponse
    {
        $receipt = $this->receipts->createDraft($request->validated());

        return redirect()
            ->route('goods-receipts.show', $receipt)
            ->with('success', __('สร้างใบรับสินค้า :no แล้ว — ยังเป็นร่าง ยังไม่กระทบสต็อก', ['no' => $receipt->receipt_no]));
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('view', $goodsReceipt);

        $goodsReceipt->load(['items.product', 'supplier', 'warehouse', 'creator', 'poster', 'movements']);

        return view('goods-receipts.show', ['receipt' => $goodsReceipt]);
    }

    public function edit(GoodsReceipt $goodsReceipt): View
    {
        $this->authorize('update', $goodsReceipt);

        $goodsReceipt->load('items.product');

        return view('goods-receipts.edit', [
            'receipt' => $goodsReceipt,
            ...$this->formData(),
        ]);
    }

    public function update(GoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->receipts->updateDraft($goodsReceipt, $request->validated());

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt)
            ->with('success', __('แก้ไขใบรับสินค้า :no แล้ว', ['no' => $goodsReceipt->receipt_no]));
    }

    /**
     * บันทึกเข้าสต็อกจริง — ย้อนกลับไม่ได้
     */
    public function post(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('post', $goodsReceipt);

        $this->receipts->post($goodsReceipt);

        return redirect()
            ->route('goods-receipts.show', $goodsReceipt)
            ->with('success', __('บันทึกใบ :no เข้าสต็อกแล้ว', ['no' => $goodsReceipt->receipt_no]));
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->authorize('delete', $goodsReceipt);

        $this->receipts->cancel($goodsReceipt);

        return redirect()
            ->route('goods-receipts.index')
            ->with('success', __('ยกเลิกใบรับสินค้า :no แล้ว', ['no' => $goodsReceipt->receipt_no]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'suppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
            'products' => $this->productOptions(),
        ];
    }
}
