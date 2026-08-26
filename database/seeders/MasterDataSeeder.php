<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * หมวดหมู่ ยี่ห้อ และคลังสินค้าตาม spec 3.1
 *
 * รันซ้ำได้ — ใช้ updateOrCreate ทุกตัว
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();
        $this->seedWarehouses();
    }

    private function seedCategories(): void
    {
        /** @var array<string, array{en: string, children: array<string, string>}> $tree */
        $tree = [
            'Power & Backup' => [
                'en' => 'Power & Backup',
                'children' => [
                    'UPS' => 'UPS',
                    'แบตเตอรี่' => 'Battery',
                    'ATS' => 'ATS',
                    'เครื่องกำเนิดไฟฟ้า' => 'Generator',
                    'ตู้ MDB' => 'MDB',
                ],
            ],
            'Cooling' => [
                'en' => 'Cooling',
                'children' => [
                    'Precision Air' => 'Precision Air',
                    'In-row Cooling' => 'In-row Cooling',
                    'Containment' => 'Containment',
                ],
            ],
            'Rack & Infra' => [
                'en' => 'Rack & Infrastructure',
                'children' => [
                    'ตู้ Rack' => 'Rack',
                    'PDU' => 'PDU',
                    'สายและอุปกรณ์เดินสาย' => 'Cable',
                    'Raised Floor' => 'Raised Floor',
                ],
            ],
            'Monitoring & Safety' => [
                'en' => 'Monitoring & Safety',
                'children' => [
                    'เซ็นเซอร์อุณหภูมิ/ความชื้น' => 'Temp / Humidity Sensor',
                    'เซ็นเซอร์น้ำรั่ว' => 'Water-leak Sensor',
                    'ระบบดับเพลิง' => 'Fire Suppression',
                    'ระบบควบคุมการเข้าออก' => 'Access Control',
                    'CCTV' => 'CCTV',
                ],
            ],
            'Consumable & Spare' => [
                'en' => 'Consumable & Spare',
                'children' => [
                    'ไส้กรอง' => 'Filter',
                    'พัดลม' => 'Fan',
                    'คาปาซิเตอร์' => 'Capacitor',
                    'สายพาน' => 'Belt',
                ],
            ],
        ];

        $rootOrder = 0;

        foreach ($tree as $rootNameTh => $root) {
            $parent = Category::updateOrCreate(
                ['name_th' => $rootNameTh, 'parent_id' => null],
                ['name_en' => $root['en'], 'sort_order' => $rootOrder += 10],
            );

            $childOrder = 0;

            foreach ($root['children'] as $childNameTh => $childNameEn) {
                Category::updateOrCreate(
                    ['name_th' => $childNameTh, 'parent_id' => $parent->id],
                    ['name_en' => $childNameEn, 'sort_order' => $childOrder += 10],
                );
            }
        }
    }

    private function seedBrands(): void
    {
        $brands = [
            'APC by Schneider Electric', 'Eaton', 'Vertiv', 'Delta', 'Socomec',
            'CyberPower', 'Yuasa', 'CSB Battery', 'Panasonic', 'Stulz',
            'Rittal', 'Legrand', 'Raritan', 'Cummins', 'ABB',
            'Siemens', 'Honeywell', 'Hikvision', 'AKCP', 'RLE Technologies',
        ];

        foreach ($brands as $name) {
            Brand::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    private function seedWarehouses(): void
    {
        $warehouses = [
            ['code' => 'HQ', 'name' => 'คลังสำนักงานใหญ่', 'address' => 'สำนักงานใหญ่ TEXSON', 'is_default' => true],
            ['code' => 'VAN', 'name' => 'สต็อกรถบริการ', 'address' => 'รถบริการภาคสนาม', 'is_default' => false],
            ['code' => 'CONSIGN', 'name' => 'สต็อกฝากหน้างานลูกค้า', 'address' => 'หน้างานลูกค้า', 'is_default' => false],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                [...$warehouse, 'is_active' => true],
            );
        }
    }
}
