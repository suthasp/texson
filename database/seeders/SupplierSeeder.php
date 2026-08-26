<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * ผู้ขายตั้งต้น — ข้อมูลสมมติ (ADR-004) แต่รหัสตรงกับที่ ProductSeeder อ้างถึง
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['code' => 'SUP-0001', 'name' => 'ชไนเดอร์ อิเล็คทริค (ประเทศไทย)', 'contact_name' => 'ฝ่ายขายโครงการ', 'lead_time_days' => 30],
            ['code' => 'SUP-0002', 'name' => 'อีตัน อิเล็คทริค (ประเทศไทย)', 'contact_name' => 'ฝ่ายขาย Power Quality', 'lead_time_days' => 45],
            ['code' => 'SUP-0003', 'name' => 'เวอร์ทิฟ (ประเทศไทย)', 'contact_name' => 'ฝ่ายขาย Critical Infrastructure', 'lead_time_days' => 45],
            ['code' => 'SUP-0004', 'name' => 'เดลต้า อีเลคโทรนิคส์ (ประเทศไทย)', 'contact_name' => 'ฝ่ายขาย Mission Critical', 'lead_time_days' => 60],
            ['code' => 'SUP-0005', 'name' => 'ไทยแบตเตอรี่ ซัพพลาย', 'contact_name' => 'ฝ่ายขายแบตเตอรี่สำรองไฟ', 'lead_time_days' => 14],
            ['code' => 'SUP-0006', 'name' => 'พาวเวอร์ เจนเนอเรชั่น เอ็นจิเนียริ่ง', 'contact_name' => 'ฝ่ายขายเครื่องกำเนิดไฟฟ้า', 'lead_time_days' => 90],
            ['code' => 'SUP-0007', 'name' => 'สตูลซ์ เอเชีย แปซิฟิก', 'contact_name' => 'ฝ่ายขาย Precision Cooling', 'lead_time_days' => 90],
            ['code' => 'SUP-0008', 'name' => 'ริตทัล (ประเทศไทย)', 'contact_name' => 'ฝ่ายขาย IT Infrastructure', 'lead_time_days' => 45],
            ['code' => 'SUP-0009', 'name' => 'ดาต้าเซ็นเตอร์ คอมโพเนนท์ ซัพพลาย', 'contact_name' => 'ฝ่ายขายอุปกรณ์ Rack', 'lead_time_days' => 21],
            ['code' => 'SUP-0010', 'name' => 'มอนิเตอร์ริ่ง โซลูชั่น เอเชีย', 'contact_name' => 'ฝ่ายขายระบบมอนิเตอร์', 'lead_time_days' => 30],
            ['code' => 'SUP-0011', 'name' => 'เซฟตี้ ซิสเต็ม เอ็นจิเนียริ่ง', 'contact_name' => 'ฝ่ายขายระบบความปลอดภัย', 'lead_time_days' => 60],
            ['code' => 'SUP-0012', 'name' => 'ซีเคียวริตี้ วิชั่น เทรดดิ้ง', 'contact_name' => 'ฝ่ายขายกล้องวงจรปิด', 'lead_time_days' => 14],
        ];

        foreach ($suppliers as $index => $supplier) {
            Supplier::updateOrCreate(
                ['code' => $supplier['code']],
                [
                    ...$supplier,
                    'tax_id' => str_pad((string) (1000000000000 + $index), 13, '0', STR_PAD_LEFT),
                    'phone' => sprintf('02-%03d-%04d', 200 + $index, 1000 + $index),
                    'email' => 'sales'.($index + 1).'@example.co.th',
                    'is_active' => true,
                ],
            );
        }
    }
}
