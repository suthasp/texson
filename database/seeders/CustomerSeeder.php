<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PriceTier;
use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * ลูกค้าตัวอย่างพร้อมผู้ติดต่อและหน้างาน — ข้อมูลสมมติ (ADR-004)
 *
 * หน้างานมีตั้งแต่ Phase 1 เพราะใบเสนอราคาต้องเลือก site ได้ และ Phase 2 จะผูก asset ที่นี่
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            $customer = Customer::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name_th' => $row['name_th'],
                    'name_en' => $row['name_en'],
                    'tax_id' => $row['tax_id'],
                    'branch_code' => $row['branch_code'],
                    'address_line' => $row['address_line'],
                    'subdistrict' => $row['subdistrict'],
                    'district' => $row['district'],
                    'province' => $row['province'],
                    'postcode' => $row['postcode'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'credit_term_days' => $row['credit_term_days'],
                    'payment_terms' => $row['payment_terms'],
                    'price_tier' => $row['price_tier'],
                    'is_active' => true,
                ],
            );

            foreach ($row['contacts'] as $index => $contact) {
                $customer->contacts()->updateOrCreate(
                    ['name' => $contact['name']],
                    [...$contact, 'is_primary' => $index === 0],
                );
            }

            $primaryContactId = $customer->contacts()->where('is_primary', true)->value('id');

            foreach ($row['sites'] as $site) {
                $customer->sites()->updateOrCreate(
                    ['site_code' => $site['site_code']],
                    [...$site, 'primary_contact_id' => $primaryContactId, 'is_active' => true],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            [
                'code' => 'CUS-0001',
                'name_th' => 'บริษัท สยาม ดาต้าเซ็นเตอร์ จำกัด',
                'name_en' => 'Siam Data Center Co., Ltd.',
                'tax_id' => '0105551000011', 'branch_code' => '00000',
                'address_line' => '99 อาคารสยามทาวเวอร์ ชั้น 18', 'subdistrict' => 'ปทุมวัน',
                'district' => 'ปทุมวัน', 'province' => 'กรุงเทพมหานคร', 'postcode' => '10330',
                'phone' => '02-100-2000', 'email' => 'procurement@example.co.th',
                'credit_term_days' => 30, 'payment_terms' => 'โอนเงินภายใน 30 วันหลังส่งของ',
                'price_tier' => PriceTier::Project,
                'contacts' => [
                    ['name' => 'คุณสมชาย ใจดี', 'position' => 'ผู้จัดการฝ่าย Facility', 'phone' => '081-234-5678', 'email' => 'somchai@example.co.th', 'line_id' => 'somchai.dc'],
                    ['name' => 'คุณวราภรณ์ สุขใจ', 'position' => 'เจ้าหน้าที่จัดซื้อ', 'phone' => '081-234-5679', 'email' => 'waraporn@example.co.th', 'line_id' => null],
                ],
                'sites' => [
                    ['site_code' => 'DC-01', 'site_name' => 'DC ชั้น 3 อาคาร A', 'address_line' => '99 อาคารสยามทาวเวอร์ ชั้น 3', 'province' => 'กรุงเทพมหานคร', 'access_note' => 'ต้องแจ้งล่วงหน้า 1 วัน และแลกบัตรที่ รปภ. ชั้น 1'],
                    ['site_code' => 'DC-02', 'site_name' => 'DR Site บางนา', 'address_line' => '55 ถนนบางนา-ตราด กม.18', 'province' => 'สมุทรปราการ', 'access_note' => 'เข้าได้เฉพาะเวลา 09:00-17:00 วันจันทร์-ศุกร์'],
                ],
            ],
            [
                'code' => 'CUS-0002',
                'name_th' => 'บริษัท ไทยแบงก์กิ้ง เทคโนโลยี จำกัด (มหาชน)',
                'name_en' => 'Thai Banking Technology PCL',
                'tax_id' => '0107545000022', 'branch_code' => '00000',
                'address_line' => '1 อาคารสำนักงานใหญ่ ถนนสาทรใต้', 'subdistrict' => 'ทุ่งมหาเมฆ',
                'district' => 'สาทร', 'province' => 'กรุงเทพมหานคร', 'postcode' => '10120',
                'phone' => '02-200-3000', 'email' => 'it.procurement@example.co.th',
                'credit_term_days' => 60, 'payment_terms' => 'วางบิลทุกวันที่ 25 จ่ายภายใน 60 วัน',
                'price_tier' => PriceTier::Project,
                'contacts' => [
                    ['name' => 'คุณประเสริฐ มั่นคง', 'position' => 'ผู้อำนวยการฝ่าย IT Infrastructure', 'phone' => '089-111-2222', 'email' => 'prasert@example.co.th', 'line_id' => null],
                ],
                'sites' => [
                    ['site_code' => 'HQ-DC', 'site_name' => 'Data Center สำนักงานใหญ่ ชั้น B1', 'address_line' => '1 ถนนสาทรใต้ ชั้น B1', 'province' => 'กรุงเทพมหานคร', 'access_note' => 'พื้นที่หวงห้าม ต้องขออนุมัติล่วงหน้า 3 วันทำการ'],
                ],
            ],
            [
                'code' => 'CUS-0003',
                'name_th' => 'บริษัท อีสเทิร์น อินดัสเทรียล พาร์ค จำกัด',
                'name_en' => 'Eastern Industrial Park Co., Ltd.',
                'tax_id' => '0205548000033', 'branch_code' => '00001',
                'address_line' => '188 หมู่ 5 นิคมอุตสาหกรรมอมตะซิตี้', 'subdistrict' => 'ดอนหัวฬ่อ',
                'district' => 'เมืองชลบุรี', 'province' => 'ชลบุรี', 'postcode' => '20000',
                'phone' => '038-300-400', 'email' => 'maintenance@example.co.th',
                'credit_term_days' => 30, 'payment_terms' => 'เช็คลงวันที่ 30 วัน',
                'price_tier' => PriceTier::Standard,
                'contacts' => [
                    ['name' => 'คุณอนุชา ทองดี', 'position' => 'หัวหน้าแผนกซ่อมบำรุง', 'phone' => '086-555-7777', 'email' => 'anucha@example.co.th', 'line_id' => 'anucha.eip'],
                ],
                'sites' => [
                    ['site_code' => 'PLANT-1', 'site_name' => 'ห้อง Server โรงงาน 1', 'address_line' => '188 หมู่ 5 อาคารโรงงาน 1', 'province' => 'ชลบุรี', 'access_note' => 'ต้องใส่อุปกรณ์นิรภัยครบก่อนเข้าพื้นที่'],
                ],
            ],
            [
                'code' => 'CUS-0004',
                'name_th' => 'ห้างหุ้นส่วนจำกัด เน็ตเวิร์ค โซลูชั่น พลัส',
                'name_en' => 'Network Solution Plus Ltd., Part.',
                'tax_id' => '0303556000044', 'branch_code' => '00000',
                'address_line' => '45/12 ถนนงามวงศ์วาน', 'subdistrict' => 'บางเขน',
                'district' => 'เมืองนนทบุรี', 'province' => 'นนทบุรี', 'postcode' => '11000',
                'phone' => '02-580-1234', 'email' => 'sales@example.co.th',
                'credit_term_days' => 15, 'payment_terms' => 'โอนเงินภายใน 15 วัน',
                'price_tier' => PriceTier::Dealer,
                'contacts' => [
                    ['name' => 'คุณกิตติ วงศ์เจริญ', 'position' => 'กรรมการผู้จัดการ', 'phone' => '081-888-9999', 'email' => 'kitti@example.co.th', 'line_id' => 'kitti.nsp'],
                ],
                'sites' => [
                    ['site_code' => 'WH-01', 'site_name' => 'คลังสินค้านนทบุรี', 'address_line' => '45/12 ถนนงามวงศ์วาน', 'province' => 'นนทบุรี', 'access_note' => null],
                ],
            ],
            [
                'code' => 'CUS-0005',
                'name_th' => 'มหาวิทยาลัยเทคโนโลยีภาคเหนือ',
                'name_en' => 'Northern Technology University',
                'tax_id' => '0994000000055', 'branch_code' => '00000',
                'address_line' => '239 ถนนห้วยแก้ว', 'subdistrict' => 'สุเทพ',
                'district' => 'เมืองเชียงใหม่', 'province' => 'เชียงใหม่', 'postcode' => '50200',
                'phone' => '053-900-100', 'email' => 'itcenter@example.ac.th',
                'credit_term_days' => 45, 'payment_terms' => 'ตามระเบียบพัสดุราชการ',
                'price_tier' => PriceTier::Standard,
                'contacts' => [
                    ['name' => 'อาจารย์นพดล ศรีวิไล', 'position' => 'ผู้อำนวยการสำนักคอมพิวเตอร์', 'phone' => '084-222-3333', 'email' => 'noppadol@example.ac.th', 'line_id' => null],
                ],
                'sites' => [
                    ['site_code' => 'IT-DC', 'site_name' => 'Data Center สำนักคอมพิวเตอร์', 'address_line' => '239 ถนนห้วยแก้ว อาคารสำนักคอมพิวเตอร์ ชั้น 2', 'province' => 'เชียงใหม่', 'access_note' => 'ติดต่อเจ้าหน้าที่เวรก่อนเข้าพื้นที่'],
                ],
            ],
        ];
    }
}
