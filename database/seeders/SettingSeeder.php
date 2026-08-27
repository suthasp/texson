<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * ค่าตั้งตั้งต้น — ข้อมูลบริษัทเป็นข้อมูลสมมติตาม ADR-004
 * แก้ได้เองที่หน้า ตั้งค่าระบบ โดยไม่ต้อง deploy ใหม่
 *
 * รันซ้ำได้ และจะไม่ทับค่าที่ผู้ดูแลแก้ไว้แล้ว
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Setting::query()->pluck('key')->all();

        foreach ($this->values() as $key => $value) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            Setting::create([
                'key' => $key,
                'value' => $value,
                'group' => SettingKey::from($key)->group(),
            ]);
        }

        app(SettingService::class)->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        return [
            SettingKey::CompanyNameTh->value => 'บริษัท เท็กซัน จำกัด',
            SettingKey::CompanyNameEn->value => 'TEXSON Co., Ltd.',
            SettingKey::CompanyTaxId->value => '0105558000123',
            SettingKey::CompanyBranchCode->value => '00000',
            SettingKey::CompanyAddressTh->value => "เลขที่ 199/8 อาคารเท็กซัน ชั้น 5\nถนนรัชดาภิเษก แขวงดินแดง เขตดินแดง\nกรุงเทพมหานคร 10400",
            SettingKey::CompanyAddressEn->value => "199/8 TEXSON Building, 5th Floor\nRatchadaphisek Road, Din Daeng\nBangkok 10400, Thailand",
            SettingKey::CompanyPhone->value => '02-123-4567',
            SettingKey::CompanyEmail->value => 'sales@texson.co.th',
            SettingKey::CompanyWebsite->value => 'www.texson.co.th',
            SettingKey::CompanySignerName->value => 'สุธาศิน ประเสริฐ',
            SettingKey::CompanySignerPosition->value => 'ผู้จัดการฝ่ายขาย',

            SettingKey::VatRate->value => '7.00',
            SettingKey::QuoteValidDays->value => 30,
            SettingKey::PaymentTerms->value => 'เครดิต 30 วัน นับจากวันที่ส่งมอบ',
            SettingKey::DeliveryTerms->value => 'ส่งมอบ ณ สถานที่ของลูกค้า (กรุงเทพฯ และปริมณฑล)',
            SettingKey::LeadTimeNote->value => 'ตามที่ระบุในแต่ละรายการ นับจากวันที่ได้รับใบสั่งซื้อ',
            SettingKey::TermsAndConditionsTh->value => implode("\n", [
                '1. ราคานี้รวมค่าขนส่งภายในกรุงเทพฯ และปริมณฑลแล้ว นอกพื้นที่คิดค่าใช้จ่ายเพิ่มตามระยะทาง',
                '2. ราคานี้ยังไม่รวมงานเดินสายไฟ งานโครงสร้าง และงานที่ไม่ได้ระบุไว้ในรายการข้างต้น',
                '3. รับประกันสินค้าตามเงื่อนไขของผู้ผลิต นับจากวันที่ส่งมอบ',
                '4. กรณีสั่งซื้อกรุณาออกใบสั่งซื้อ (PO) อ้างอิงเลขที่ใบเสนอราคานี้',
                '5. บริษัทขอสงวนสิทธิ์ในการเปลี่ยนแปลงราคาหากพ้นกำหนดยืนราคา',
            ]),
            SettingKey::TermsAndConditionsEn->value => implode("\n", [
                '1. Price includes delivery within Bangkok and vicinity. Upcountry delivery is charged separately.',
                '2. Price excludes electrical wiring, structural work, and any item not listed above.',
                '3. Products are warranted per the manufacturer terms, starting from the delivery date.',
                '4. Please issue a purchase order referencing this quotation number.',
                '5. Prices are subject to change after the validity date stated above.',
            ]),

            SettingKey::ApprovalMaxDiscountPercent->value => '15.00',
            SettingKey::ApprovalMinMarginPercent->value => '10.00',
            SettingKey::ApprovalMaxGrandTotal->value => '500000.00',
        ];
    }
}
