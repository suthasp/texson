<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ใบเสนอราคา QT-YYYYMM-#### (spec 3.3)
 *
 * เรื่อง unique ของ quote_no (ADR-009):
 * revision ของใบเดิมใช้ quote_no เดิมแล้วเพิ่ม revision ทีละ 1 ตามชื่อไฟล์ในสเปกข้อ 5
 * (QT-202608-0007_rev1.pdf) — unique จึงต้องเป็นคู่ (quote_no, revision) ไม่ใช่ quote_no เดี่ยว
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('quote_no', 30);
            $table->unsignedSmallInteger('revision')->default(0);

            // ใบก่อนหน้าในสายการแก้ไข — null คือใบต้นสาย
            $table->foreignId('parent_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->foreignId('customer_site_id')->nullable()->constrained('customer_sites')->nullOnDelete();
            $table->foreignId('sales_user_id')->constrained('users')->restrictOnDelete();

            $table->date('issue_date');
            $table->date('valid_until');
            $table->char('currency', 3)->default('THB');
            $table->enum('price_tier', ['standard', 'dealer', 'project'])->default('standard');

            // ── ยอดเงิน — decimal ทั้งหมดตามกฎเหล็กข้อ 4 ห้ามใช้ float ──
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('after_discount', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(7.00);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            // ผลรวมของ cost_snapshot ทุกบรรทัด — เก็บไว้เพื่อไม่ต้อง join items ตอนทำรายงาน margin
            $table->decimal('cost_total', 15, 2)->default(0);

            $table->enum('status', [
                'draft', 'pending_approval', 'sent', 'accepted', 'rejected', 'expired', 'cancelled',
            ])->default('draft');

            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('lead_time_note')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('lost_reason')->nullable();

            // ถูกแทนที่ด้วย revision ใหม่เมื่อไร (ADR-002) — แยกจาก status เพื่อไม่ให้รายงานเพี้ยน
            $table->timestamp('superseded_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['quote_no', 'revision']);
            $table->index('quote_no');
            $table->index(['customer_id', 'status']);
            $table->index(['sales_user_id', 'status']);
            $table->index('issue_date');
            // ใช้โดย job ที่เปลี่ยนใบหมดอายุทุกเช้า 06:00
            $table->index(['status', 'valid_until']);
        });

        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');

            // null ได้ เพราะบรรทัดค่าแรง/ค่าขนส่ง/ข้อความ ไม่ผูกกับสินค้าในระบบ
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('item_type', ['product', 'service', 'labour', 'freight', 'note'])->default('product');

            // ── snapshot: คัดลอกมาตอนออกใบ แก้สินค้าภายหลังต้องไม่กระทบใบเก่า (spec 3.3) ──
            $table->string('sku_snapshot', 60)->nullable();
            $table->text('description');
            $table->string('uom', 20)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('cost_snapshot', 15, 2)->default(0);

            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'line_no']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
