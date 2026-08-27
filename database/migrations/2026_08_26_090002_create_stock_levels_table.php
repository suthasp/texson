<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ยอดคงเหลือรายสินค้าต่อคลัง — เป็นยอดสรุปที่ต้องเท่ากับผลรวมของ stock_movements เสมอ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_on_hand', 15, 3)->default(0);
            $table->decimal('qty_reserved', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
