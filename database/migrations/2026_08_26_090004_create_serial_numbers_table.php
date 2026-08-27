<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serial รายชิ้นของสินค้าที่ is_serialized เช่น UPS และแบตเตอรี่ (spec 3.2)
 *
 * sales_order_id ยังไม่ผูก FK เพราะตาราง sales_orders จะสร้างใน Phase 4
 * จึงเก็บเป็นคอลัมน์เปล่าไว้ก่อนแล้วค่อยเพิ่ม constraint ทีหลัง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('serial_no', 100);
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', ['in_stock', 'reserved', 'sold', 'installed', 'rma', 'scrapped'])
                ->default('in_stock');

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_site_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('sales_order_id')->nullable();

            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'serial_no']);
            $table->index(['status', 'warehouse_id']);
            $table->index('serial_no');
            $table->index('sales_order_id');
            $table->index('warranty_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_numbers');
    }
};
