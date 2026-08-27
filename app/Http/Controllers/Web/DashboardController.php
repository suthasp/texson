<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard — สรุปข้อมูลหลัก สถานะคลัง และงานขายที่ค้างอยู่
 *
 * ตัวเลขยอดขายรายเดือน/รายปี และ win rate เต็มรูปแบบจะเพิ่มใน Phase 5
 * ตรงนี้แสดงเฉพาะสิ่งที่ต้องลงมือทำวันนี้: ใบรออนุมัติและใบใกล้หมดอายุ (spec 7)
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $canSeeStock = $user->can('viewAny', StockLevel::class);
        $canSeeQuotations = $user->can('viewAny', Quotation::class);
        $canSeeOrders = $user->can('viewAny', SalesOrder::class);

        // ใบที่ผู้ใช้คนนี้มีสิทธิ์เห็น — sales เห็นเฉพาะของตัวเอง (spec 8)
        $visibleQuotations = fn () => Quotation::query()->visibleTo($user);

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
            'canSeeQuotations' => $canSeeQuotations,
            'quotationStats' => $canSeeQuotations ? [
                'open' => $visibleQuotations()->open()->whereNull('superseded_at')->count(),
                'pending_approval' => $visibleQuotations()->where('status', QuotationStatus::PendingApproval)->count(),
                'expiring' => $visibleQuotations()->expiringWithin(7)->count(),
            ] : [],
            // ใบที่ต้องอนุมัติ แสดงเฉพาะคนที่อนุมัติได้จริง ไม่งั้นเป็นข้อมูลที่กดอะไรต่อไม่ได้
            'awaitingApproval' => $canSeeQuotations && $user->can(PermissionName::QuotationApprove->value)
                ? $visibleQuotations()
                    ->where('status', QuotationStatus::PendingApproval)
                    ->whereNull('approved_at')
                    ->with(['customer:id,name_th', 'salesUser:id,name'])
                    ->orderBy('issue_date')
                    ->limit(5)
                    ->get()
                : collect(),
            'expiringQuotations' => $canSeeQuotations
                ? $visibleQuotations()->expiringWithin(7)->with('customer:id,name_th')->orderBy('valid_until')->limit(5)->get()
                : collect(),
            'canSeeOrders' => $canSeeOrders,
            'orderStats' => $canSeeOrders ? [
                'open' => SalesOrder::query()->visibleTo($user)->open()->count(),
                'pending' => SalesOrder::query()->visibleTo($user)->where('status', SalesOrderStatus::Pending)->count(),
            ] : [],
            // ใบที่ยืนยันแล้วและยังมีของค้างส่ง — คิวงานของฝ่ายคลัง
            'ordersToShip' => $canSeeOrders
                ? SalesOrder::query()
                    ->visibleTo($user)
                    ->whereIn('status', [SalesOrderStatus::Reserved, SalesOrderStatus::PartiallyDelivered])
                    ->with(['customer:id,name_th', 'warehouse:id,code', 'items'])
                    ->orderByRaw('required_date is null, required_date')
                    ->limit(5)
                    ->get()
                : collect(),
            'recentProducts' => Product::query()->with(['category', 'brand'])->latest()->limit(5)->get(),
            'recentCustomers' => Customer::query()->latest()->limit(5)->get(),
        ]);
    }
}
