<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\BrandController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CustomerContactController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerPersonalDataController;
use App\Http\Controllers\Web\CustomerSiteController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeliveryController;
use App\Http\Controllers\Web\GoodsReceiptController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\QuotationController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SalesOrderController;
use App\Http\Controllers\Web\SerialNumberController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\StockAdjustmentController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\StockTransferController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // ── ลูกค้า ──
    /*
     * งานตาม PDPA อยู่หน้าเดียวต่อลูกค้าหนึ่งราย (spec 8)
     * ผูกแบบ withTrashed เพราะคำขอเข้าถึง/ลบข้อมูลมักมาถึงหลังลูกค้าถูกปิดบัญชีไปแล้ว
     */
    Route::controller(CustomerPersonalDataController::class)
        ->prefix('customers/{customer}')
        ->name('customers.')
        ->group(function (): void {
            Route::get('personal-data', 'show')->name('personal-data')->withTrashed();
            Route::get('personal-data/download', 'download')->name('personal-data.download')->withTrashed();
            Route::delete('personal-data', 'erase')->name('personal-data.erase')->withTrashed();
            Route::post('restore', 'restore')->name('restore')->withTrashed();
        });

    Route::resource('customers', CustomerController::class);

    Route::scopeBindings()->group(function (): void {
        Route::resource('customers.contacts', CustomerContactController::class)
            ->except(['index', 'show'])
            ->parameters(['contacts' => 'contact']);

        Route::resource('customers.sites', CustomerSiteController::class)
            ->except(['index', 'show'])
            ->parameters(['sites' => 'site']);
    });

    // ── สินค้าและข้อมูลประกอบ ──
    Route::resource('products', ProductController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('warehouses', WarehouseController::class)->except(['show']);

    // ── สต็อก (อ่านอย่างเดียว) ──
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('stock/ledger', [StockController::class, 'ledger'])->name('stock.ledger');
    Route::get('serial-numbers', [SerialNumberController::class, 'index'])->name('serial-numbers.index');

    // ── เอกสารคลัง ──
    // post แยกออกมาเป็น route ของตัวเอง เพราะเป็นการกระทำที่ย้อนกลับไม่ได้
    // และใช้สิทธิ์คนละตัวกับการแก้ไขใบ
    Route::post('goods-receipts/{goods_receipt}/post', [GoodsReceiptController::class, 'post'])
        ->name('goods-receipts.post');
    Route::resource('goods-receipts', GoodsReceiptController::class)
        ->parameters(['goods-receipts' => 'goods_receipt']);

    Route::post('stock-transfers/{stock_transfer}/post', [StockTransferController::class, 'post'])
        ->name('stock-transfers.post');
    Route::resource('stock-transfers', StockTransferController::class)
        ->parameters(['stock-transfers' => 'stock_transfer']);

    Route::post('stock-adjustments/{stock_adjustment}/post', [StockAdjustmentController::class, 'post'])
        ->name('stock-adjustments.post');
    Route::resource('stock-adjustments', StockAdjustmentController::class)
        ->parameters(['stock-adjustments' => 'stock_adjustment']);

    // ── ใบเสนอราคา ──
    // การกระทำที่เปลี่ยนสถานะแยกเป็น route ของตัวเอง เพราะใช้สิทธิ์คนละตัวกับการแก้ไขใบ
    Route::controller(QuotationController::class)->prefix('quotations')->name('quotations.')->group(function (): void {
        Route::get('{quotation}/pdf', 'pdf')->name('pdf');
        Route::post('{quotation}/submit', 'submit')->name('submit');
        Route::post('{quotation}/approve', 'approve')->name('approve');
        Route::post('{quotation}/return', 'returnToDraft')->name('return');
        Route::post('{quotation}/send', 'send')->name('send');
        Route::post('{quotation}/accept', 'accept')->name('accept');
        Route::post('{quotation}/reject', 'reject')->name('reject');
        Route::post('{quotation}/cancel', 'cancel')->name('cancel');
        Route::post('{quotation}/revise', 'revise')->name('revise');
        Route::post('{quotation}/convert-to-so', 'convertToSalesOrder')->name('convert');
    });
    Route::resource('quotations', QuotationController::class);

    // ── ใบสั่งขาย ──
    // ไม่มี create/store เพราะใบสั่งขายเกิดจากการแปลงใบเสนอราคาเท่านั้น (spec 4.3)
    Route::controller(SalesOrderController::class)->prefix('sales-orders')->name('sales-orders.')->group(function (): void {
        Route::post('{sales_order}/confirm', 'confirm')->name('confirm');
        Route::post('{sales_order}/cancel', 'cancel')->name('cancel');
        Route::get('{sales_order}/purchase-order-file', 'purchaseOrderFile')->name('po-file');
    });
    Route::resource('sales-orders', SalesOrderController::class)
        ->only(['index', 'show', 'edit', 'update'])
        ->parameters(['sales-orders' => 'sales_order']);

    // ── ใบส่งของ ──
    // เปิดจากใบสั่งขายเสมอ จึงซ้อนอยู่ใต้ sales-orders ตอนสร้าง
    Route::get('sales-orders/{sales_order}/deliveries/create', [DeliveryController::class, 'create'])
        ->name('sales-orders.deliveries.create');
    Route::post('sales-orders/{sales_order}/deliveries', [DeliveryController::class, 'store'])
        ->name('sales-orders.deliveries.store');

    Route::post('deliveries/{delivery}/post', [DeliveryController::class, 'post'])->name('deliveries.post');
    Route::resource('deliveries', DeliveryController::class)->except(['create', 'store']);

    // ── รายงานและไฟล์ส่งออก ──
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('export/products', 'exportProducts')->name('export.products');
        Route::get('export/quotations', 'exportQuotations')->name('export.quotations');
        Route::get('export/ledger', 'exportLedger')->name('export.ledger');
    });

    // ── ผู้ใช้งานระบบ ──
    Route::resource('users', UserController::class)->except(['show']);

    // ── ประวัติการใช้งาน (audit trail) ──
    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

    // ── ตั้งค่าระบบ ──
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('settings/asset/{key}', [SettingController::class, 'asset'])->name('settings.asset');

    // ── โปรไฟล์ของตัวเอง ──
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
