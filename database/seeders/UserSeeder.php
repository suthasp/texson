<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ผู้ใช้ตั้งต้นหนึ่งคนต่อหนึ่ง role สำหรับทดสอบสิทธิ์บนเครื่องพัฒนา
 *
 * รหัสผ่านชุดนี้ใช้ได้เฉพาะ local/testing — ดู guard ใน run()
 */
class UserSeeder extends Seeder
{
    private const DEV_PASSWORD = 'texson1234';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('ข้ามการสร้างผู้ใช้ตัวอย่าง เพราะกำลังรันบน production');

            return;
        }

        $users = [
            ['employee_code' => 'EMP001', 'name' => 'ผู้ดูแลระบบ TEXSON', 'email' => 'admin@texson.local', 'role' => RoleName::Admin],
            ['employee_code' => 'EMP002', 'name' => 'ผู้จัดการฝ่ายขาย', 'email' => 'manager@texson.local', 'role' => RoleName::SalesManager],
            ['employee_code' => 'EMP003', 'name' => 'พนักงานขาย 1', 'email' => 'sales1@texson.local', 'role' => RoleName::Sales],
            ['employee_code' => 'EMP004', 'name' => 'พนักงานขาย 2', 'email' => 'sales2@texson.local', 'role' => RoleName::Sales],
            ['employee_code' => 'EMP005', 'name' => 'เจ้าหน้าที่คลังสินค้า', 'email' => 'warehouse@texson.local', 'role' => RoleName::Warehouse],
            ['employee_code' => 'EMP006', 'name' => 'วิศวกรบริการ', 'email' => 'engineer@texson.local', 'role' => RoleName::Engineer],
            ['employee_code' => 'EMP007', 'name' => 'ผู้ดูรายงาน', 'email' => 'viewer@texson.local', 'role' => RoleName::Viewer],
        ];

        foreach ($users as $row) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'employee_code' => $row['employee_code'],
                    'name' => $row['name'],
                    'password' => Hash::make(self::DEV_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$row['role']->value]);
        }

        $this->command?->info('ผู้ใช้ตัวอย่างพร้อมแล้ว — รหัสผ่านทุกบัญชีคือ '.self::DEV_PASSWORD);
    }
}
