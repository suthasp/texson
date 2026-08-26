<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Uom;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * สินค้าตั้งต้น 30 SKU ครอบคลุมทุกหมวดตาม spec 3.1
 *
 * ราคาและ part number เป็นข้อมูลสมมติที่สมจริง (ดู ADR-004 ใน docs/DECISIONS.md)
 * ราคาทุนจริงเป็นข้อมูลอ่อนไหว ไม่ควรอยู่ใน git — แก้ผ่านหน้าจอหลัง deploy
 *
 * รันซ้ำได้ — ใช้ updateOrCreate โดยยึด sku เป็นคีย์
 */
class ProductSeeder extends Seeder
{
    /** อัตราบวกราคาจากทุน แยกตามระดับราคา (spec 4.5) */
    private const MARKUP = ['list' => 1.35, 'dealer' => 1.22, 'project' => 1.15];

    public function run(): void
    {
        $categories = Category::query()->whereNotNull('parent_id')->pluck('id', 'name_th');
        $brands = Brand::query()->pluck('id', 'name');
        $suppliers = Supplier::query()->pluck('id', 'code');

        foreach ($this->rows() as $row) {
            $cost = (float) $row['cost'];

            $product = Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name_th' => $row['name_th'],
                    'name_en' => $row['name_en'],
                    'category_id' => $categories[$row['category']] ?? null,
                    'brand_id' => $brands[$row['brand']] ?? null,
                    'model' => $row['model'],
                    'part_number' => $row['part_number'],
                    'uom' => $row['uom'],
                    'cost_price' => $cost,
                    'list_price' => round($cost * self::MARKUP['list'], 2),
                    'dealer_price' => round($cost * self::MARKUP['dealer'], 2),
                    'project_price' => round($cost * self::MARKUP['project'], 2),
                    'is_serialized' => $row['serialized'] ?? false,
                    'track_lot' => $row['lot'] ?? false,
                    'min_stock' => $row['min_stock'],
                    'reorder_qty' => $row['reorder_qty'],
                    'lead_time_days' => $row['lead_time'],
                    'warranty_months' => $row['warranty'],
                    'spec' => $row['spec'] ?? null,
                    'is_active' => true,
                ],
            );

            if (isset($row['supplier']) && isset($suppliers[$row['supplier']])) {
                $product->suppliers()->syncWithoutDetaching([
                    $suppliers[$row['supplier']] => [
                        'supplier_sku' => $row['part_number'],
                        'cost_price' => $cost,
                        'lead_time_days' => $row['lead_time'],
                        'is_preferred' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            // ── Power & Backup / UPS ──
            [
                'sku' => 'UPS-APC-SRT10K', 'name_th' => 'เครื่องสำรองไฟ APC Smart-UPS SRT 10kVA', 'name_en' => 'APC Smart-UPS SRT 10kVA',
                'category' => 'UPS', 'brand' => 'APC by Schneider Electric', 'model' => 'SRT10KXLI', 'part_number' => 'SRT10KXLI',
                'uom' => Uom::Set, 'cost' => 285000, 'serialized' => true, 'min_stock' => 1, 'reorder_qty' => 2,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0001',
                'spec' => ['kva' => '10', 'phase' => '1P', 'voltage' => '230', 'form' => 'Rack/Tower'],
            ],
            [
                'sku' => 'UPS-APC-SRT20K', 'name_th' => 'เครื่องสำรองไฟ APC Smart-UPS SRT 20kVA', 'name_en' => 'APC Smart-UPS SRT 20kVA',
                'category' => 'UPS', 'brand' => 'APC by Schneider Electric', 'model' => 'SRT20KXLI', 'part_number' => 'SRT20KXLI',
                'uom' => Uom::Set, 'cost' => 520000, 'serialized' => true, 'min_stock' => 1, 'reorder_qty' => 1,
                'lead_time' => 45, 'warranty' => 24, 'supplier' => 'SUP-0001',
                'spec' => ['kva' => '20', 'phase' => '1P', 'voltage' => '230', 'form' => 'Rack/Tower'],
            ],
            [
                'sku' => 'UPS-EAT-93PS20', 'name_th' => 'เครื่องสำรองไฟ Eaton 93PS 20kVA', 'name_en' => 'Eaton 93PS 20kVA',
                'category' => 'UPS', 'brand' => 'Eaton', 'model' => '93PS-20', 'part_number' => 'P-1032100003',
                'uom' => Uom::Set, 'cost' => 495000, 'serialized' => true, 'min_stock' => 1, 'reorder_qty' => 1,
                'lead_time' => 60, 'warranty' => 24, 'supplier' => 'SUP-0002',
                'spec' => ['kva' => '20', 'phase' => '3P', 'voltage' => '380', 'efficiency' => '96%'],
            ],
            [
                'sku' => 'UPS-VER-GXT5-10K', 'name_th' => 'เครื่องสำรองไฟ Vertiv Liebert GXT5 10kVA', 'name_en' => 'Vertiv Liebert GXT5 10kVA',
                'category' => 'UPS', 'brand' => 'Vertiv', 'model' => 'GXT5-10KIRT5UXLN', 'part_number' => 'GXT5-10KIRT5UXLN',
                'uom' => Uom::Set, 'cost' => 268000, 'serialized' => true, 'min_stock' => 1, 'reorder_qty' => 2,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0003',
                'spec' => ['kva' => '10', 'phase' => '1P', 'voltage' => '230', 'form' => 'Rack 5U'],
            ],
            [
                'sku' => 'UPS-DEL-MOD100', 'name_th' => 'เครื่องสำรองไฟ Delta Modulon DPH 100kVA', 'name_en' => 'Delta Modulon DPH 100kVA',
                'category' => 'UPS', 'brand' => 'Delta', 'model' => 'DPH-100K', 'part_number' => 'UPD1001NA000035',
                'uom' => Uom::Set, 'cost' => 1450000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 90, 'warranty' => 24, 'supplier' => 'SUP-0004',
                'spec' => ['kva' => '100', 'phase' => '3P', 'voltage' => '380', 'topology' => 'Modular'],
            ],

            // ── Power & Backup / แบตเตอรี่ ──
            [
                'sku' => 'BAT-YUA-NP7-12', 'name_th' => 'แบตเตอรี่ Yuasa NP7-12 12V 7Ah', 'name_en' => 'Yuasa NP7-12 12V 7Ah',
                'category' => 'แบตเตอรี่', 'brand' => 'Yuasa', 'model' => 'NP7-12', 'part_number' => 'NP7-12',
                'uom' => Uom::Pcs, 'cost' => 620, 'serialized' => true, 'lot' => true, 'min_stock' => 40, 'reorder_qty' => 100,
                'lead_time' => 14, 'warranty' => 12, 'supplier' => 'SUP-0005',
                'spec' => ['voltage' => '12V', 'capacity' => '7Ah', 'type' => 'VRLA AGM'],
            ],
            [
                'sku' => 'BAT-CSB-GP12120', 'name_th' => 'แบตเตอรี่ CSB GP12120 12V 12Ah', 'name_en' => 'CSB GP12120 12V 12Ah',
                'category' => 'แบตเตอรี่', 'brand' => 'CSB Battery', 'model' => 'GP12120', 'part_number' => 'GP12120F2',
                'uom' => Uom::Pcs, 'cost' => 890, 'serialized' => true, 'lot' => true, 'min_stock' => 30, 'reorder_qty' => 60,
                'lead_time' => 14, 'warranty' => 12, 'supplier' => 'SUP-0005',
                'spec' => ['voltage' => '12V', 'capacity' => '12Ah', 'type' => 'VRLA AGM'],
            ],
            [
                'sku' => 'BAT-PAN-LCX12100', 'name_th' => 'แบตเตอรี่ Panasonic LC-X12100 12V 100Ah', 'name_en' => 'Panasonic LC-X12100 12V 100Ah',
                'category' => 'แบตเตอรี่', 'brand' => 'Panasonic', 'model' => 'LC-X12100', 'part_number' => 'LC-X12100P',
                'uom' => Uom::Pcs, 'cost' => 7800, 'serialized' => true, 'lot' => true, 'min_stock' => 8, 'reorder_qty' => 20,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0005',
                'spec' => ['voltage' => '12V', 'capacity' => '100Ah', 'type' => 'VRLA AGM'],
            ],
            [
                'sku' => 'BAT-YUA-SWL2500', 'name_th' => 'แบตเตอรี่ Yuasa SWL2500 High Rate', 'name_en' => 'Yuasa SWL2500 High Rate',
                'category' => 'แบตเตอรี่', 'brand' => 'Yuasa', 'model' => 'SWL2500', 'part_number' => 'SWL2500',
                'uom' => Uom::Pcs, 'cost' => 5200, 'serialized' => true, 'lot' => true, 'min_stock' => 12, 'reorder_qty' => 24,
                'lead_time' => 21, 'warranty' => 24, 'supplier' => 'SUP-0005',
                'spec' => ['voltage' => '12V', 'capacity' => '93Ah', 'type' => 'High Rate VRLA'],
            ],

            // ── Power & Backup / ATS, Generator, MDB ──
            [
                'sku' => 'ATS-SOC-ATYS3S-250', 'name_th' => 'ชุดสลับไฟอัตโนมัติ Socomec ATyS 3s 250A', 'name_en' => 'Socomec ATyS 3s 250A ATS',
                'category' => 'ATS', 'brand' => 'Socomec', 'model' => 'ATyS 3s 250A', 'part_number' => '95234025',
                'uom' => Uom::Set, 'cost' => 185000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 60, 'warranty' => 24, 'supplier' => 'SUP-0002',
                'spec' => ['current' => '250A', 'phase' => '4P', 'voltage' => '400'],
            ],
            [
                'sku' => 'GEN-CUM-C275D5', 'name_th' => 'เครื่องกำเนิดไฟฟ้า Cummins C275 D5 275kVA', 'name_en' => 'Cummins C275 D5 Generator 275kVA',
                'category' => 'เครื่องกำเนิดไฟฟ้า', 'brand' => 'Cummins', 'model' => 'C275D5', 'part_number' => 'C275D5-SG',
                'uom' => Uom::Set, 'cost' => 2350000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 120, 'warranty' => 24, 'supplier' => 'SUP-0006',
                'spec' => ['kva' => '275', 'phase' => '3P', 'fuel' => 'Diesel', 'enclosure' => 'Soundproof'],
            ],
            [
                'sku' => 'MDB-ABB-400A', 'name_th' => 'ตู้ MDB ABB 400A พร้อม ACB', 'name_en' => 'ABB Main Distribution Board 400A',
                'category' => 'ตู้ MDB', 'brand' => 'ABB', 'model' => 'MDB-400', 'part_number' => 'MDB400-ACB',
                'uom' => Uom::Set, 'cost' => 320000, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 75, 'warranty' => 12, 'supplier' => 'SUP-0006',
                'spec' => ['current' => '400A', 'phase' => '3P+N', 'form' => 'Form 3b'],
            ],

            // ── Cooling ──
            [
                'sku' => 'CRAC-STU-CW60', 'name_th' => 'เครื่องปรับอากาศแม่นยำ Stulz CyberAir 3PRO 60kW', 'name_en' => 'Stulz CyberAir 3PRO 60kW',
                'category' => 'Precision Air', 'brand' => 'Stulz', 'model' => 'CW60', 'part_number' => 'CA3PRO-CW60',
                'uom' => Uom::Set, 'cost' => 1180000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 120, 'warranty' => 24, 'supplier' => 'SUP-0007',
                'spec' => ['capacity_kw' => '60', 'type' => 'Chilled Water', 'airflow' => 'Downflow'],
            ],
            [
                'sku' => 'CRAC-VER-PDX70', 'name_th' => 'เครื่องปรับอากาศแม่นยำ Vertiv Liebert PDX 70kW', 'name_en' => 'Vertiv Liebert PDX 70kW',
                'category' => 'Precision Air', 'brand' => 'Vertiv', 'model' => 'PDX070', 'part_number' => 'PDX070-DX',
                'uom' => Uom::Set, 'cost' => 1290000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 120, 'warranty' => 24, 'supplier' => 'SUP-0003',
                'spec' => ['capacity_kw' => '70', 'type' => 'DX', 'airflow' => 'Downflow'],
            ],
            [
                'sku' => 'INROW-APC-ACRD501', 'name_th' => 'ระบบทำความเย็น In-row APC ACRD501', 'name_en' => 'APC InRow RD ACRD501',
                'category' => 'In-row Cooling', 'brand' => 'APC by Schneider Electric', 'model' => 'ACRD501', 'part_number' => 'ACRD501',
                'uom' => Uom::Set, 'cost' => 685000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 90, 'warranty' => 24, 'supplier' => 'SUP-0001',
                'spec' => ['capacity_kw' => '30', 'type' => 'DX', 'width_mm' => '600'],
            ],
            [
                'sku' => 'CONT-RIT-HOT-12R', 'name_th' => 'ระบบกั้นลมร้อน Rittal Hot Aisle 12 ตู้', 'name_en' => 'Rittal Hot Aisle Containment 12 Racks',
                'category' => 'Containment', 'brand' => 'Rittal', 'model' => 'HAC-12', 'part_number' => '7859.120',
                'uom' => Uom::Set, 'cost' => 420000, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 75, 'warranty' => 12, 'supplier' => 'SUP-0008',
                'spec' => ['racks' => '12', 'type' => 'Hot Aisle', 'roof' => 'Sliding'],
            ],

            // ── Rack & Infra ──
            [
                'sku' => 'RACK-RIT-TS42U', 'name_th' => 'ตู้ Rack Rittal TS IT 42U 800x1200', 'name_en' => 'Rittal TS IT 42U 800x1200',
                'category' => 'ตู้ Rack', 'brand' => 'Rittal', 'model' => 'TS-IT-42U', 'part_number' => '5504.120',
                'uom' => Uom::Set, 'cost' => 58000, 'serialized' => true, 'min_stock' => 2, 'reorder_qty' => 5,
                'lead_time' => 45, 'warranty' => 24, 'supplier' => 'SUP-0008',
                'spec' => ['height_u' => '42', 'width_mm' => '800', 'depth_mm' => '1200', 'load_kg' => '1500'],
            ],
            [
                'sku' => 'RACK-APC-NS42U', 'name_th' => 'ตู้ Rack APC NetShelter SX 42U', 'name_en' => 'APC NetShelter SX 42U',
                'category' => 'ตู้ Rack', 'brand' => 'APC by Schneider Electric', 'model' => 'AR3300', 'part_number' => 'AR3300',
                'uom' => Uom::Set, 'cost' => 46500, 'serialized' => true, 'min_stock' => 2, 'reorder_qty' => 5,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0001',
                'spec' => ['height_u' => '42', 'width_mm' => '600', 'depth_mm' => '1070', 'load_kg' => '1364'],
            ],
            [
                'sku' => 'PDU-RAR-PX3-5190', 'name_th' => 'PDU วัดค่าได้ Raritan PX3-5190 32A', 'name_en' => 'Raritan PX3-5190 Metered PDU 32A',
                'category' => 'PDU', 'brand' => 'Raritan', 'model' => 'PX3-5190R', 'part_number' => 'PX3-5190R',
                'uom' => Uom::Pcs, 'cost' => 42000, 'serialized' => true, 'min_stock' => 4, 'reorder_qty' => 10,
                'lead_time' => 45, 'warranty' => 24, 'supplier' => 'SUP-0009',
                'spec' => ['current' => '32A', 'phase' => '1P', 'outlets' => '24', 'metering' => 'Per-outlet'],
            ],
            [
                'sku' => 'PDU-APC-AP8959', 'name_th' => 'PDU สั่งงานได้ APC AP8959 32A', 'name_en' => 'APC Rack PDU 2G Switched AP8959',
                'category' => 'PDU', 'brand' => 'APC by Schneider Electric', 'model' => 'AP8959', 'part_number' => 'AP8959',
                'uom' => Uom::Pcs, 'cost' => 38500, 'serialized' => true, 'min_stock' => 4, 'reorder_qty' => 10,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0001',
                'spec' => ['current' => '32A', 'phase' => '1P', 'outlets' => '24', 'metering' => 'Switched'],
            ],
            [
                'sku' => 'CBL-LEG-C6A-305', 'name_th' => 'สาย LAN Legrand Cat6A U/UTP กล่อง 305 เมตร', 'name_en' => 'Legrand Cat6A U/UTP 305m Box',
                'category' => 'สายและอุปกรณ์เดินสาย', 'brand' => 'Legrand', 'model' => 'CAT6A-UTP', 'part_number' => '032756',
                'uom' => Uom::Box, 'cost' => 8900, 'lot' => true, 'min_stock' => 6, 'reorder_qty' => 12,
                'lead_time' => 14, 'warranty' => 12, 'supplier' => 'SUP-0009',
                'spec' => ['category' => 'Cat6A', 'length_m' => '305', 'jacket' => 'LSZH'],
            ],
            [
                'sku' => 'FLR-RIT-600-1000', 'name_th' => 'แผ่นพื้นยกระดับ 600x600 รับน้ำหนัก 1000 กก.', 'name_en' => 'Raised Floor Panel 600x600 1000kg',
                'category' => 'Raised Floor', 'brand' => 'Rittal', 'model' => 'RF600-1000', 'part_number' => 'RF600-1000HD',
                'uom' => Uom::Pcs, 'cost' => 2450, 'min_stock' => 50, 'reorder_qty' => 200,
                'lead_time' => 45, 'warranty' => 12, 'supplier' => 'SUP-0008',
                'spec' => ['size_mm' => '600x600', 'load_kg' => '1000', 'finish' => 'HPL'],
            ],

            // ── Monitoring & Safety ──
            [
                'sku' => 'SEN-AKC-THS300', 'name_th' => 'เซ็นเซอร์อุณหภูมิและความชื้น AKCP THS300', 'name_en' => 'AKCP Temperature & Humidity Sensor THS300',
                'category' => 'เซ็นเซอร์อุณหภูมิ/ความชื้น', 'brand' => 'AKCP', 'model' => 'THS300', 'part_number' => 'THS00',
                'uom' => Uom::Pcs, 'cost' => 6800, 'min_stock' => 10, 'reorder_qty' => 20,
                'lead_time' => 21, 'warranty' => 24, 'supplier' => 'SUP-0010',
                'spec' => ['range_c' => '-40 ถึง 85', 'accuracy' => '±0.5°C', 'cable_m' => '3'],
            ],
            [
                'sku' => 'SEN-RLE-SC-01', 'name_th' => 'เซ็นเซอร์ตรวจจับน้ำรั่วแบบจุด RLE SC', 'name_en' => 'RLE Spot Water Leak Detector SC',
                'category' => 'เซ็นเซอร์น้ำรั่ว', 'brand' => 'RLE Technologies', 'model' => 'SC-1', 'part_number' => 'SC',
                'uom' => Uom::Pcs, 'cost' => 4200, 'min_stock' => 10, 'reorder_qty' => 20,
                'lead_time' => 30, 'warranty' => 24, 'supplier' => 'SUP-0010',
                'spec' => ['type' => 'Spot', 'output' => 'Dry contact'],
            ],
            [
                'sku' => 'FIRE-SIE-FM200-40', 'name_th' => 'ระบบดับเพลิง FM-200 ถัง 40 ลิตร Siemens', 'name_en' => 'Siemens FM-200 Suppression 40L',
                'category' => 'ระบบดับเพลิง', 'brand' => 'Siemens', 'model' => 'FM200-40L', 'part_number' => 'FM200-40L-SET',
                'uom' => Uom::Set, 'cost' => 285000, 'serialized' => true, 'min_stock' => 0, 'reorder_qty' => 1,
                'lead_time' => 90, 'warranty' => 24, 'supplier' => 'SUP-0011',
                'spec' => ['agent' => 'FM-200', 'volume_l' => '40', 'coverage_m3' => '120'],
            ],
            [
                'sku' => 'ACS-HON-PRO32', 'name_th' => 'ชุดควบคุมการเข้าออก Honeywell Pro32 4 ประตู', 'name_en' => 'Honeywell Pro32 Access Controller 4-Door',
                'category' => 'ระบบควบคุมการเข้าออก', 'brand' => 'Honeywell', 'model' => 'PRO32IC', 'part_number' => 'PRO32IC-4D',
                'uom' => Uom::Set, 'cost' => 68000, 'serialized' => true, 'min_stock' => 1, 'reorder_qty' => 2,
                'lead_time' => 45, 'warranty' => 24, 'supplier' => 'SUP-0011',
                'spec' => ['doors' => '4', 'interface' => 'TCP/IP', 'readers' => '8'],
            ],
            [
                'sku' => 'CCTV-HIK-2CD2143', 'name_th' => 'กล้องวงจรปิด Hikvision DS-2CD2143G2 4MP', 'name_en' => 'Hikvision DS-2CD2143G2 4MP Dome',
                'category' => 'CCTV', 'brand' => 'Hikvision', 'model' => 'DS-2CD2143G2-I', 'part_number' => 'DS-2CD2143G2-I',
                'uom' => Uom::Pcs, 'cost' => 4900, 'serialized' => true, 'min_stock' => 8, 'reorder_qty' => 16,
                'lead_time' => 14, 'warranty' => 24, 'supplier' => 'SUP-0012',
                'spec' => ['resolution' => '4MP', 'type' => 'Dome', 'ir_m' => '30', 'poe' => 'Yes'],
            ],

            // ── Consumable & Spare ──
            [
                'sku' => 'FLT-STU-G4-592', 'name_th' => 'ไส้กรองอากาศ G4 ขนาด 592x592x48 มม.', 'name_en' => 'Air Filter G4 592x592x48mm',
                'category' => 'ไส้กรอง', 'brand' => 'Stulz', 'model' => 'G4-592', 'part_number' => 'FLT-G4-592048',
                'uom' => Uom::Pcs, 'cost' => 780, 'lot' => true, 'min_stock' => 40, 'reorder_qty' => 100,
                'lead_time' => 14, 'warranty' => 0, 'supplier' => 'SUP-0007',
                'spec' => ['grade' => 'G4', 'size_mm' => '592x592x48'],
            ],
            [
                'sku' => 'FAN-VER-EC450', 'name_th' => 'พัดลม EC ขนาด 450 มม. สำหรับ CRAC', 'name_en' => 'EC Fan 450mm for CRAC',
                'category' => 'พัดลม', 'brand' => 'Vertiv', 'model' => 'EC450', 'part_number' => 'FAN-EC450-24',
                'uom' => Uom::Pcs, 'cost' => 18500, 'serialized' => true, 'min_stock' => 4, 'reorder_qty' => 8,
                'lead_time' => 45, 'warranty' => 12, 'supplier' => 'SUP-0003',
                'spec' => ['diameter_mm' => '450', 'type' => 'EC', 'voltage' => '230'],
            ],
            [
                'sku' => 'CAP-DEL-470-450', 'name_th' => 'คาปาซิเตอร์ 470uF 450V สำหรับ UPS', 'name_en' => 'Capacitor 470uF 450V for UPS',
                'category' => 'คาปาซิเตอร์', 'brand' => 'Delta', 'model' => 'CAP470-450', 'part_number' => 'CAP-470UF-450V',
                'uom' => Uom::Pcs, 'cost' => 1650, 'lot' => true, 'min_stock' => 20, 'reorder_qty' => 50,
                'lead_time' => 30, 'warranty' => 12, 'supplier' => 'SUP-0004',
                'spec' => ['capacitance' => '470uF', 'voltage' => '450V', 'temp_c' => '105'],
            ],
            [
                'sku' => 'BLT-STU-SPZ1200', 'name_th' => 'สายพาน SPZ1200 สำหรับพัดลม CRAC', 'name_en' => 'Belt SPZ1200 for CRAC Fan',
                'category' => 'สายพาน', 'brand' => 'Stulz', 'model' => 'SPZ1200', 'part_number' => 'BLT-SPZ1200',
                'uom' => Uom::Pcs, 'cost' => 420, 'lot' => true, 'min_stock' => 20, 'reorder_qty' => 40,
                'lead_time' => 14, 'warranty' => 0, 'supplier' => 'SUP-0007',
                'spec' => ['profile' => 'SPZ', 'length_mm' => '1200'],
            ],
        ];
    }
}
