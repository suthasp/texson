<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * หน้างานของลูกค้า — ใช้เต็มที่ใน Phase 2 (PM/CM) แต่ Phase 1 ให้ใบเสนอราคาเลือก site ได้แล้ว
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('site_code', 30);
            $table->string('site_name');
            $table->string('address_line')->nullable();
            $table->string('province', 100)->nullable();
            $table->text('access_note')->nullable();
            $table->foreignId('primary_contact_id')->nullable()
                ->constrained('customer_contacts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['customer_id', 'site_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sites');
    }
};
