<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * แดชบอร์ดตามสเปกข้อ 7
 *
 * ยอดขายเดือนนี้/ปีนี้ · ใบเสนอราคารออนุมัติ · win rate ·
 * สินค้าต่ำกว่า min stock · ใบที่ใกล้หมดอายุใน 7 วัน
 *
 * ตัวเลขทั้งหมดมาจาก ReportService ตัวเดียวกับหน้ารายงาน — ถ้าสองหน้าให้ตัวเลขต่างกัน
 * แปลว่ามีคนเขียน query ซ้ำที่ไหนสักแห่ง
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $canSeeStock = $user->can('viewAny', StockLevel::class);
        $canSeeQuotations = $user->can('viewAny', Quotation::class);
        $canSeeOrders = $user->can('viewAny', SalesOrder::class);
        $canSeeReports = $user->can(PermissionName::ReportViewAny->value);

        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();
        $today = Carbon::now()->endOfDay();

        return view('dashboard', [
            'canSeeStock' => $canSeeStock,
            'canSeeQuotations' => $canSeeQuotations,
            'canSeeOrders' => $canSeeOrders,
            'canSeeReports' => $canSeeReports,

            // ── ยอดขายเดือนนี้ / ปีนี้ (spec 7) ──
            'salesThisMonth' => $canSeeOrders ? $this->reports->salesSummary($monthStart, $today, $user) : null,
            'salesThisYear' => $canSeeOrders ? $this->reports->salesSummary($yearStart, $today, $user) : null,
            'quotationsThisYear' => $canSeeQuotations ? $this->reports->quotationSummary($yearStart, $today, $user) : null,
            'actions' => $this->reports->actionItems($user),

            // ── คิวงานที่ต้องลงมือทำ ──
            'awaitingApproval' => $canSeeQuotations && $user->can(PermissionName::QuotationApprove->value)
                ? Quotation::query()
                    ->visibleTo($user)
                    ->where('status', QuotationStatus::PendingApproval)
                    ->whereNull('approved_at')
                    ->with(['customer:id,name_th', 'salesUser:id,name'])
                    ->orderBy('issue_date')
                    ->limit(5)
                    ->get()
                : collect(),

            'expiringQuotations' => $canSeeQuotations
                ? Quotation::query()
                    ->visibleTo($user)
                    ->expiringWithin(7)
                    ->with('customer:id,name_th')
                    ->orderBy('valid_until')
                    ->limit(5)
                    ->get()
                : collect(),

            'ordersToShip' => $canSeeOrders
                ? SalesOrder::query()
                    ->visibleTo($user)
                    ->whereIn('status', [SalesOrderStatus::Reserved, SalesOrderStatus::PartiallyDelivered])
                    ->with(['customer:id,name_th', 'warehouse:id,code', 'items'])
                    ->orderByRaw('required_date is null, required_date')
                    ->limit(5)
                    ->get()
                : collect(),

            // ── สต็อก ──
            'lowStock' => $canSeeStock
                ? StockLevel::query()->belowMinimum()->with(['product', 'warehouse'])->limit(8)->get()
                : collect(),
            'lowStockCount' => $canSeeStock ? StockLevel::query()->belowMinimum()->count() : 0,
            'warrantyExpiring' => $canSeeStock
                ? SerialNumber::query()
                    ->whereNotNull('warranty_end')
                    ->whereBetween('warranty_end', [Carbon::now()->toDateString(), Carbon::now()->addDays(90)->toDateString()])
                    ->count()
                : 0,
            'draftDocuments' => $canSeeStock ? [
                'goods_receipts' => GoodsReceipt::query()->where('status', StockDocumentStatus::Draft)->count(),
                'transfers' => StockTransfer::query()->where('status', StockDocumentStatus::Draft)->count(),
                'adjustments' => StockAdjustment::query()->where('status', StockDocumentStatus::Draft)->count(),
            ] : [],
        ]);
    }
}
