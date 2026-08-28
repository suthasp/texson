<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\StockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 (spec 6)
|--------------------------------------------------------------------------
|
| ยืนยันตัวตนด้วย Sanctum token · rate limit 60 ครั้ง/นาที
| ทุก response ผ่าน API Resource และมีรูป {"data": ..., "meta": ...}
|
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // ── ขอ token — ไม่ต้องล็อกอินก่อน จึงคุมด้วย 5 ครั้ง/นาที ตาม spec 8 ──
    Route::post('auth/token', [AuthController::class, 'token'])
        ->middleware('throttle:5,1')
        ->name('auth.token');

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {

        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [AuthController::class, 'revoke'])->name('auth.revoke');

        // ── สินค้า ──
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products/{product}/stock', [ProductController::class, 'stock'])->name('products.stock');

        // ── ลูกค้า ──
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        // ── สต็อก ──
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::get('stock/ledger', [StockController::class, 'ledger'])->name('stock.ledger');

        // ── ใบเสนอราคา ──
        // action ที่เปลี่ยนสถานะต้องมาก่อน route ที่รับ {quotation}
        // ไม่งั้น 'quotations/{quotation}' จะจับ path เหล่านี้ไปก่อน
        Route::prefix('quotations')->name('quotations.')->controller(QuotationController::class)->group(function (): void {
            Route::get('{quotation}/pdf', 'pdf')->name('pdf');
            Route::post('{quotation}/submit', 'submit')->name('submit');
            Route::post('{quotation}/approve', 'approve')->name('approve');
            Route::post('{quotation}/send', 'send')->name('send');
            Route::post('{quotation}/accept', 'accept')->name('accept');
            Route::post('{quotation}/reject', 'reject')->name('reject');
            Route::post('{quotation}/cancel', 'cancel')->name('cancel');
            Route::post('{quotation}/revise', 'revise')->name('revise');
            Route::post('{quotation}/convert-to-so', 'convertToSalesOrder')->name('convert');
        });

        Route::apiResource('quotations', QuotationController::class);

        // ── ใบสั่งขาย ──
        // ไม่มี store/update ของรายการ — ใบเกิดจากการแปลงใบเสนอราคาเท่านั้น (spec 4.3)
        Route::prefix('sales-orders')->name('sales-orders.')->group(function (): void {
            Route::post('{sales_order}/confirm', [SalesOrderController::class, 'confirm'])->name('confirm');
            Route::post('{sales_order}/cancel', [SalesOrderController::class, 'cancel'])->name('cancel');
            Route::get('{sales_order}/outstanding', [DeliveryController::class, 'outstanding'])->name('outstanding');
            Route::post('{sales_order}/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        });

        Route::get('sales-orders', [SalesOrderController::class, 'index'])->name('sales-orders.index');
        Route::get('sales-orders/{sales_order}', [SalesOrderController::class, 'show'])->name('sales-orders.show');

        // ── ใบส่งของ ──
        Route::post('deliveries/{delivery}/post', [DeliveryController::class, 'post'])->name('deliveries.post');
        Route::apiResource('deliveries', DeliveryController::class)->except(['store']);

        // ── รายงาน ──
        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales-summary');
    });
});
