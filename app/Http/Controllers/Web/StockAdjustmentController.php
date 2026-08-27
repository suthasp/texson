<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Concerns\ProvidesProductOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    use ProvidesProductOptions;

    public function __construct(private readonly StockAdjustmentService $adjustments) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $adjustments = StockAdjustment::query()
            ->with(['warehouse', 'creator'])
            ->withCount('items')
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->string('reason')->toString()))
            ->orderByDesc('adjusted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'statuses' => StockDocumentStatus::options(),
            'reasons' => StockAdjustmentReason::options(),
            'filters' => $request->only(['q', 'status', 'reason']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('stock-adjustments.create', [
            'adjustment' => new StockAdjustment(['adjusted_at' => now()]),
            ...$this->formData(),
        ]);
    }

    public function store(StockAdjustmentRequest $request): RedirectResponse
    {
        $adjustment = $this->adjustments->createDraft($request->validated());

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', __('สร้างใบปรับปรุง :no แล้ว — ยังเป็นร่าง ยังไม่กระทบสต็อก', ['no' => $adjustment->adjust_no]));
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        $stockAdjustment->load(['items.product', 'warehouse', 'creator', 'poster', 'movements']);

        return view('stock-adjustments.show', ['adjustment' => $stockAdjustment]);
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('update', $stockAdjustment);

        $stockAdjustment->load('items.product');

        return view('stock-adjustments.edit', [
            'adjustment' => $stockAdjustment,
            ...$this->formData(),
        ]);
    }

    public function update(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->adjustments->updateDraft($stockAdjustment, $request->validated());

        return redirect()
            ->route('stock-adjustments.show', $stockAdjustment)
            ->with('success', __('แก้ไขใบปรับปรุง :no แล้ว', ['no' => $stockAdjustment->adjust_no]));
    }

    public function post(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('post', $stockAdjustment);

        $this->adjustments->post($stockAdjustment);

        return redirect()
            ->route('stock-adjustments.show', $stockAdjustment)
            ->with('success', __('ปรับปรุงสต็อกตามใบ :no แล้ว', ['no' => $stockAdjustment->adjust_no]));
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('delete', $stockAdjustment);

        $this->adjustments->cancel($stockAdjustment);

        return redirect()
            ->route('stock-adjustments.index')
            ->with('success', __('ยกเลิกใบปรับปรุง :no แล้ว', ['no' => $stockAdjustment->adjust_no]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
            'reasons' => StockAdjustmentReason::options(),
            'products' => $this->productOptions(),
        ];
    }
}
