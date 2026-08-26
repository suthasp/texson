<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name_th');
            $table->string('name_en')->nullable();

            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->string('model', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->enum('uom', ['pcs', 'set', 'box', 'roll', 'm'])->default('pcs');

            // เงินทุกช่อง decimal(15,2) — ห้าม float (spec 2 กฎเหล็กข้อ 4)
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('list_price', 15, 2)->default(0);
            $table->decimal('dealer_price', 15, 2)->default(0);
            $table->decimal('project_price', 15, 2)->default(0);

            $table->boolean('is_serialized')->default(false);
            $table->boolean('track_lot')->default(false);

            // จำนวนใช้ decimal(15,3) รองรับหน่วยที่มีเศษ เช่น เมตร
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->decimal('reorder_qty', 15, 3)->default(0);

            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->unsignedSmallInteger('warranty_months')->default(0);

            $table->json('spec')->nullable();
            $table->string('image_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('part_number');
            $table->index(['category_id', 'is_active']);
            $table->index('brand_id');
            $table->fullText(['name_th', 'name_en', 'model'], 'products_search_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
