<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\View\View;

/**
 * Dashboard ของ Phase 1 แสดงเฉพาะสรุปข้อมูลหลัก
 * ตัวเลขยอดขาย / win rate / ใบใกล้หมดอายุ จะเพิ่มใน Phase 5
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                'customers' => Customer::query()->active()->count(),
                'products' => Product::query()->active()->count(),
                'suppliers' => Supplier::query()->active()->count(),
                'brands' => Brand::query()->active()->count(),
                'warehouses' => Warehouse::query()->active()->count(),
            ],
            'recentProducts' => Product::query()->with(['category', 'brand'])->latest()->limit(5)->get(),
            'recentCustomers' => Customer::query()->latest()->limit(5)->get(),
        ]);
    }
}
