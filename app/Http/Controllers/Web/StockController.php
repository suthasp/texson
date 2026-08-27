<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ยอดคงเหลือและประวัติการเคลื่อนไหว — อ่านอย่างเดียว
 *
 * การเปลี่ยนสต็อกทำผ่านเอกสาร (ใบรับสินค้า / ใบโอน / ใบปรับปรุง) เท่านั้น
 */
class StockController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockLevel::class);

        $query = StockLevel::query()
            ->with(['product.category', 'product.brand', 'warehouse'])
            ->whereHas('product', fn ($q) => $q->when(
                $request->filled('q'),
                fn ($p) => $p->search($request->string('q')->toString()),
            )->when(
                $request->filled('category_id'),
                fn ($p) => $p->inCategory($request->integer('category_id')),
            ))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when(! $request->boolean('include_zero'), fn ($q) => $q->where(function ($sub): void {
                $sub->where('qty_on_hand', '!=', 0)->orWhere('qty_reserved', '!=', 0);
            }));

        if ($request->boolean('low_stock')) {
            $query->belowMinimum();
        }

        $levels = $query
            ->orderBy(Warehouse::query()->select('code')->whereColumn('warehouses.id', 'stock_levels.warehouse_id'))
            ->paginate(30)
            ->withQueryString();

        return view('stock.index', [
            'levels' => $levels,
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
            'categories' => Category::query()->with('children')->roots()->orderBy('sort_order')->get(),
            'lowStockCount' => StockLevel::query()->belowMinimum()->count(),
            'filters' => $request->only(['q', 'warehouse_id', 'category_id', 'low_stock', 'include_zero']),
        ]);
    }

    /**
     * Ledger — ประวัติการเคลื่อนไหวทั้งหมด กรองตามสินค้า/คลัง/ช่วงวันได้
     */
    public function ledger(Request $request): View
    {
        $this->authorize('viewLedger', StockLevel::class);

        $movements = StockMovement::query()
            ->with(['product', 'warehouse', 'user'])
            ->forProduct($request->integer('product_id') ?: null)
            ->forWarehouse($request->integer('warehouse_id') ?: null)
            ->movedBetween($request->string('from')->toString(), $request->string('to')->toString())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('stock.ledger', [
            'movements' => $movements,
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'types' => StockMovementType::options(),
            'filters' => $request->only(['product_id', 'warehouse_id', 'from', 'to', 'type']),
        ]);
    }
}
