<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger ของสต็อก — append-only ห้าม update/delete (spec 3.2)
 *
 * qty เก็บพร้อมเครื่องหมาย (+ เข้า / - ออก) และ balance_after เก็บยอดหลังรายการนี้
 * ทำให้ตรวจย้อนได้ว่ายอดคงเหลือปัจจุบันมาจากรายการไหนบ้าง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            $table->enum('type', [
                'receive', 'issue', 'adjust_in', 'adjust_out',
                'transfer_in', 'transfer_out', 'return_in',
            ]);

            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 3);

            // polymorphic ไปยังเอกสารต้นทาง — ไม่ผูก enum เพื่อให้ Phase 2 ใส่ WorkOrder ได้
            $table->string('ref_type')->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            $table->string('lot_no', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'moved_at'], 'stock_movements_lookup_index');
            $table->index(['ref_type', 'ref_id']);
            $table->index('moved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
