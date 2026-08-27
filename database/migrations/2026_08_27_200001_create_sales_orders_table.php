<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบสั่งขาย SO-YYYYMM-#### (spec 3.3)
 *
 * warehouse_id ไม่ได้อยู่ในสเปกแต่จำเป็น (ADR-017): การจองของเกิดที่ stock_levels
 * ซึ่งแยกตามคลัง ถ้าไม่รู้ว่าจองจากคลังไหนก็จองไม่ได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('so_no', 30)->unique();

            // unique เพราะสเปกข้อ 4.3 ระบุว่าใบเสนอราคาหนึ่งใบสร้างใบสั่งขายได้ครั้งเดียว
            // null ได้ เพราะออร์เดอร์หน้าร้านที่ไม่ได้ผ่านใบเสนอราคาก็มี
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_site_id')->nullable()->constrained('customer_sites')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();

            // ใบสั่งซื้อฝั่งลูกค้า — ไฟล์เก็บใน storage/app/private เสิร์ฟผ่าน controller ที่เช็คสิทธิ์
            $table->string('customer_po_no', 60)->nullable();
            $table->string('customer_po_file')->nullable();

            $table->date('order_date');
            $table->date('required_date')->nullable();
            $table->char('currency', 3)->default('THB');

            // ── ยอดเงินชุดเดียวกับใบเสนอราคา ──
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('after_discount', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(7.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);

            $table->enum('status', ['pending', 'reserved', 'partially_delivered', 'delivered', 'cancelled'])
                ->default('pending');

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->text('note')->nullable();
            $table->text('internal_note')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('so_no');
            $table->index(['customer_id', 'status']);
            $table->index(['sales_user_id', 'status']);
            $table->index('order_date');
            $table->index(['status', 'required_date']);
        });

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');

            // ตามรอยกลับไปยังบรรทัดต้นทางในใบเสนอราคาได้ (spec 3.3)
            $table->foreignId('quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();

            // null สำหรับบรรทัดค่าแรง/ค่าขนส่ง/ข้อความ ที่ไม่มีของให้ตัดสต็อก
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('item_type', ['product', 'service', 'labour', 'freight', 'note'])->default('product');

            // ── snapshot จากใบเสนอราคา — แก้สินค้าภายหลังต้องไม่กระทบใบเก่า ──
            $table->string('sku_snapshot', 60)->nullable();
            $table->text('description');
            $table->string('uom', 20)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('cost_snapshot', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            // ── ความคืบหน้าของบรรทัด ──
            $table->decimal('qty_ordered', 15, 3)->default(0);
            // จองได้จริงเท่าไร — น้อยกว่า qty_ordered ได้เมื่อของไม่พอ (backorder ตาม spec 4.4)
            $table->decimal('qty_reserved', 15, 3)->default(0);
            $table->decimal('qty_delivered', 15, 3)->default(0);

            $table->timestamps();

            $table->index(['sales_order_id', 'line_no']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
