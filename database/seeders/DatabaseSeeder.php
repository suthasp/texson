<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ลำดับสำคัญ — ProductSeeder ต้องหา category, brand และ supplier เจอก่อน
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            MasterDataSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            UserSeeder::class,
            // ต้องอยู่หลัง UserSeeder เพราะใบปรับปรุงยอดยกมาต้องมีผู้ทำรายการ
            OpeningStockSeeder::class,
            // ต้องอยู่ท้ายสุด — ใบเสนอราคาตัวอย่างอ้างถึงลูกค้า สินค้า และผู้ใช้ที่สร้างไปแล้ว
            QuotationSeeder::class,
            // ต้องอยู่หลัง QuotationSeeder เพราะแปลงจากใบที่ลูกค้าตอบรับแล้วเท่านั้น
            SalesOrderSeeder::class,
        ]);
    }
}
