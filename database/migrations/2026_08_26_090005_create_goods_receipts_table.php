<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบรับสินค้า GR-YYYYMM-#### — เอกสารต้นทางของ movement type=receive
 *
 * ตรงกับ PurchaseReceipt ที่ spec ข้อ 3.2 อ้างถึงใน ref_type ของ ledger
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            // เลขที่ใบส่งของหรือ PO ฝั่งผู้ขาย ใช้กระทบยอดตอนตรวจรับ
            $table->string('reference_no', 60)->nullable();

            $table->date('received_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'received_date']);
            $table->index('supplier_id');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('lot_no', 50)->nullable();

            // serial ที่กรอกตอนสร้างใบ — ถูกแปลงเป็นแถวใน serial_numbers ตอน post
            $table->json('serial_numbers')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
