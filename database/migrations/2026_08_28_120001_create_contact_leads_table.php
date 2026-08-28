<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คำขอติดต่อจากฟอร์ม "ปรึกษาฟรี" บนหน้าเว็บสาธารณะ (ADR-029)
 *
 * ข้อมูลในตารางนี้เป็นข้อมูลส่วนบุคคลตาม PDPA เหมือน customer_contacts
 * จึง soft delete และบันทึกการแก้ไขลง activity log
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_leads', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('company')->nullable();
            // ฟอร์มให้กรอกเบอร์หรืออีเมลก็ได้ในช่องเดียว จึงเก็บเป็นข้อความตามที่กรอกมา
            $table->string('contact');
            $table->string('service_interest', 30)->nullable();
            $table->text('message')->nullable();

            $table->string('status', 20)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('internal_note')->nullable();

            // เก็บไว้ตามรอยตอนโดนยิงสแปม และดูว่าคำขอมาจากหน้าภาษาไหน
            $table->string('locale', 5)->default('th');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_leads');
    }
};
