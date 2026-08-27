<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบส่งของ DN-YYYYMM-#### (spec 3.3)
 *
 * ใช้สถานะชุดเดียวกับเอกสารคลังอื่น (draft/posted/cancelled) — post แล้วตัดสต็อกจริง
 * และแก้ไม่ได้อีก เพราะ ledger เป็น append-only
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_no', 30)->unique();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();

            // คลังที่จ่ายของจริง — ปกติคือคลังของใบสั่งขาย แต่จ่ายจากคลังอื่นได้ถ้าหน้างานเปลี่ยน
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            $table->date('delivery_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');

            $table->string('receiver_name')->nullable();
            $table->string('receiver_signature_path')->nullable();
            $table->string('vehicle_note')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'delivery_date']);
            $table->index('sales_order_id');
        });

        Schema::create('delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->restrictOnDelete();

            // null สำหรับบรรทัดค่าแรง/ค่าบริการ ที่ส่งมอบงานได้แต่ไม่มีของให้ตัดสต็อก
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('qty', 15, 3);

            // serial ที่จ่ายออกไปจริง — ถูกเปลี่ยนสถานะเป็น sold ตอน post (spec 4.4)
            $table->json('serial_numbers')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->timestamps();

            $table->index('sales_order_item_id');
            $table->index('product_id');
        });

        /*
         * ผูก FK ที่ค้างไว้ตั้งแต่ Phase 2 — ตอนนั้น sales_orders ยังไม่มี
         * ทำให้ serial ที่ขายไปแล้วตามกลับไปหาใบสั่งขายได้ และเป็นจุดเชื่อมไปยัง
         * asset ที่ต้องทำ PM ใน Phase 2 ของโรดแมป
         */
        Schema::table('serial_numbers', function (Blueprint $table): void {
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table): void {
            $table->dropForeign(['sales_order_id']);
        });

        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
