<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เลขรันนิ่งของเอกสาร แยกตามประเภทและเดือน (spec 4.1)
 *
 * doc_type เก็บเป็น string ไม่ใช่ enum เพื่อให้ Phase 2 เพิ่ม WO/SR ได้โดยไม่ต้อง migrate
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('doc_type', 10);
            $table->char('period', 6);
            $table->unsignedInteger('last_no')->default(0);
            $table->timestamps();

            $table->unique(['doc_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
