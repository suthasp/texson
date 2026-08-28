<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementType;
use App\Exports\ProductStockExport;
use App\Exports\QuotationReportExport;
use App\Exports\StockLedgerExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * หน้ารายงานและไฟล์ส่งออก Excel (spec 5, spec 10 เฟส 5)
 *
 * ตัวเลขทุกตัวมาจาก ReportService ตัวเดียวกับที่แดชบอร์ดใช้
 * หน้าจอกับไฟล์ที่ส่งออกจึงตรงกันเสมอ
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportFilterRequest $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $from = $request->from();
        $to = $request->to();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'sales' => $this->reports->salesSummary($from, $to, $user),
            'quotations' => $this->reports->quotationSummary($from, $to, $user),
            'topCustomers' => $this->reports->topCustomers($from, $to, $user),
            'topProducts' => $this->reports->topProducts($from, $to, $user),
            'monthly' => $this->reports->monthlySales($user, 6),
            'valuation' => $this->reports->stockValuation(),
            'canSeeCost' => $user->can(PermissionName::ProductViewCost->value),
            'canExport' => $user->can(PermissionName::ReportExport->value),
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'quotationStatuses' => QuotationStatus::options(),
            'movementTypes' => StockMovementType::options(),
            'filters' => $request->only(['from', 'to', 'status', 'warehouse_id', 'product_id', 'type', 'low_stock']),
        ]);
    }

    /**
     * รายการสินค้า + สต็อกคงเหลือ (spec 5)
     */
    public function exportProducts(ReportFilterRequest $request): BinaryFileResponse
    {
        $this->authorizeExport($request);

        /** @var User $user */
        $user = $request->user();

        $export = new ProductStockExport($user, $request->warehouseId(), $request->boolean('low_stock'));

        return Excel::download($export, $export->filename());
    }

    /**
     * รายงานใบเสนอราคาตามช่วงวันที่ (spec 5)
     */
    public function exportQuotations(ReportFilterRequest $request): BinaryFileResponse
    {
        $this->authorizeExport($request);

        /** @var User $user */
        $user = $request->user();

        $export = new QuotationReportExport($user, $request->from(), $request->to(), $request->quotationStatus());

        return Excel::download($export, $export->filename());
    }

    /**
     * ประวัติการเคลื่อนไหวสต็อก (spec 5)
     */
    public function exportLedger(ReportFilterRequest $request): BinaryFileResponse
    {
        $this->authorizeExport($request);

        // ledger เป็นข้อมูลต้นทุนและการเคลื่อนไหวภายใน ใช้สิทธิ์เดียวกับหน้า ledger บนเว็บ
        $this->authorize('viewLedger', StockLevel::class);

        $export = new StockLedgerExport(
            $request->from(),
            $request->to(),
            $request->productId(),
            $request->warehouseId(),
            $request->movementType(),
        );

        return Excel::download($export, $export->filename());
    }

    /**
     * การส่งออกพาข้อมูลออกนอกระบบ จึงใช้สิทธิ์แยกจากการดูบนหน้าจอ
     */
    private function authorizeExport(ReportFilterRequest $request): void
    {
        abort_unless(
            $request->user()?->can(PermissionName::ReportExport->value) ?? false,
            403,
        );
    }
}
