<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบปรับปรุงสต็อก ADJ-YYYYMM-#### (spec 3.2)
 *
 * qty_system ถูก snapshot ตอนสร้างบรรทัด แล้วคำนวณ qty_diff ใหม่ตอน post
 * เพราะยอดจริงอาจขยับระหว่างที่ใบยังเป็น draft
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('adjust_no', 30)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('reason', ['stock_count', 'damaged', 'lost', 'found', 'opening']);
            $table->timestamp('adjusted_at');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->text('note')->nullable();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'adjusted_at']);
            $table->index('warehouse_id');
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_system', 15, 3)->default(0);
            $table->decimal('qty_counted', 15, 3)->default(0);
            $table->decimal('qty_diff', 15, 3)->default(0);
            $table->string('lot_no', 50)->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
    }
};
