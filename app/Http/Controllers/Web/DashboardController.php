<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard ของ Phase 2 — สรุปข้อมูลหลักและสถานะคลัง
 * ตัวเลขยอดขาย / win rate / ใบเสนอราคาใกล้หมดอายุ จะเพิ่มใน Phase 5
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canSeeStock = $user->can('viewAny', StockLevel::class);

        return view('dashboard', [
            'stats' => [
                'customers' => Customer::query()->active()->count(),
                'products' => Product::query()->active()->count(),
                'suppliers' => Supplier::query()->active()->count(),
            ],
            'canSeeStock' => $canSeeStock,
            'lowStock' => $canSeeStock
                ? StockLevel::query()->belowMinimum()->with(['product', 'warehouse'])->limit(8)->get()
                : collect(),
            'lowStockCount' => $canSeeStock ? StockLevel::query()->belowMinimum()->count() : 0,
            'draftDocuments' => $canSeeStock ? [
                'goods_receipts' => GoodsReceipt::query()->where('status', StockDocumentStatus::Draft)->count(),
                'transfers' => StockTransfer::query()->where('status', StockDocumentStatus::Draft)->count(),
                'adjustments' => StockAdjustment::query()->where('status', StockDocumentStatus::Draft)->count(),
            ] : [],
            'warrantyExpiring' => $canSeeStock
                ? SerialNumber::query()
                    ->whereNotNull('warranty_end')
                    ->whereBetween('warranty_end', [now()->toDateString(), now()->addDays(90)->toDateString()])
                    ->count()
                : 0,
            'recentProducts' => Product::query()->with(['category', 'brand'])->latest()->limit(5)->get(),
            'recentCustomers' => Customer::query()->latest()->limit(5)->get(),
        ]);
    }
}
