<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Concerns\ProvidesProductOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransferRequest;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    use ProvidesProductOptions;

    public function __construct(private readonly StockTransferService $transfers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'creator'])
            ->withCount('items')
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-transfers.index', [
            'transfers' => $transfers,
            'statuses' => StockDocumentStatus::options(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockTransfer::class);

        return view('stock-transfers.create', [
            'transfer' => new StockTransfer(['transfer_date' => now()]),
            'warehouses' => $this->warehouses(),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(StockTransferRequest $request): RedirectResponse
    {
        $transfer = $this->transfers->createDraft($request->validated());

        return redirect()
            ->route('stock-transfers.show', $transfer)
            ->with('success', __('สร้างใบโอน :no แล้ว — ยังเป็นร่าง ยังไม่กระทบสต็อก', ['no' => $transfer->transfer_no]));
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'creator', 'poster', 'movements']);

        return view('stock-transfers.show', ['transfer' => $stockTransfer]);
    }

    public function edit(StockTransfer $stockTransfer): View
    {
        $this->authorize('update', $stockTransfer);

        $stockTransfer->load('items.product');

        return view('stock-transfers.edit', [
            'transfer' => $stockTransfer,
            'warehouses' => $this->warehouses(),
            'products' => $this->productOptions(),
        ]);
    }

    public function update(StockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->transfers->updateDraft($stockTransfer, $request->validated());

        return redirect()
            ->route('stock-transfers.show', $stockTransfer)
            ->with('success', __('แก้ไขใบโอน :no แล้ว', ['no' => $stockTransfer->transfer_no]));
    }

    public function post(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('post', $stockTransfer);

        $this->transfers->post($stockTransfer);

        return redirect()
            ->route('stock-transfers.show', $stockTransfer)
            ->with('success', __('โอนตามใบ :no เรียบร้อยแล้ว', ['no' => $stockTransfer->transfer_no]));
    }

    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('delete', $stockTransfer);

        $this->transfers->cancel($stockTransfer);

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', __('ยกเลิกใบโอน :no แล้ว', ['no' => $stockTransfer->transfer_no]));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Warehouse>
     */
    private function warehouses()
    {
        return Warehouse::query()->active()->orderBy('code')->get();
    }
}
