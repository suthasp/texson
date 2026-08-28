<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDPA: ลบข้อมูลส่วนบุคคลของลูกค้าโดยยังเก็บเอกสารภาษีไว้ (ADR-024)
 *
 * เอกสารภาษีต้องเก็บ 5 ปีตามประมวลรัษฎากร ลูกค้าที่เคยมีใบเสนอราคาหรือใบสั่งขาย
 * จึงลบทิ้งทั้งแถวไม่ได้ ระบบล้างเฉพาะฟิลด์ที่เป็นข้อมูลส่วนบุคคลแล้วประทับเวลาไว้ตรงนี้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('anonymized_at')->nullable()->after('is_active');
            $table->foreignId('anonymized_by')->nullable()->after('anonymized_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('anonymized_by');
            $table->dropColumn('anonymized_at');
        });
    }
};
