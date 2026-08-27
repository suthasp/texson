<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ค่าตั้งระบบ (spec 3.4)
 *
 * เก็บข้อมูลบริษัท (ชื่อ/ที่อยู่/เลขผู้เสียภาษี/โลโก้/ลายเซ็น), อัตรา VAT,
 * เงื่อนไขเริ่มต้นของเอกสาร และเกณฑ์ที่บังคับให้ใบเสนอราคาต้องผ่านการอนุมัติ
 *
 * value เป็น json เพื่อให้เก็บได้ทั้งข้อความ ตัวเลข และ boolean โดยไม่ต้องแปลงชนิดเอง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            // ใช้ id ตัวเลขเป็น primary key แม้ key จะ unique อยู่แล้ว
            // เพราะ activity_log.subject_id ของ spatie เป็น unsignedBigInteger
            // ถ้าใช้ key เป็น primary key จะบันทึกประวัติการแก้ค่าตั้งไม่ได้ ซึ่งขัดกับ spec ข้อ 8
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();

            // จัดกลุ่มเพื่อแบ่งแท็บในหน้าตั้งค่า เช่น company / document / approval
            $table->string('group', 50)->default('general');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
