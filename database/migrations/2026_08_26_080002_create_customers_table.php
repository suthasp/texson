<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_th');
            $table->string('name_en')->nullable();

            // เลขประจำตัวผู้เสียภาษี 13 หลัก + รหัสสาขา ('00000' = สำนักงานใหญ่)
            $table->char('tax_id', 13)->nullable();
            $table->string('branch_code', 5)->default('00000');

            $table->string('address_line')->nullable();
            $table->string('subdistrict', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            $table->unsignedSmallInteger('credit_term_days')->default(30);
            $table->string('payment_terms')->nullable();
            $table->enum('price_tier', ['standard', 'dealer', 'project'])->default('standard');

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('tax_id');
            $table->index('name_th');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
