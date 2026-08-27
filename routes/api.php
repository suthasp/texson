<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReportController;
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
| endpoint ของใบสั่งขายและใบส่งของ (convert-to-so, sales-orders, deliveries)
| ยังไม่เปิดเพราะตารางอยู่ใน Phase 4 — ดู docs/API.md
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
        });

        Route::apiResource('quotations', QuotationController::class);

        // ── รายงาน ──
        Route::get('reports/low-stock', [ReportController::class, 'lowStock'])->name('reports.low-stock');
        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales-summary');
    });
});
