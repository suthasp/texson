<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\BrandController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CustomerContactController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\CustomerSiteController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GoodsReceiptController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SerialNumberController;
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

    // ── ผู้ใช้งานระบบ ──
    Route::resource('users', UserController::class)->except(['show']);

    // ── โปรไฟล์ของตัวเอง ──
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
