<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * คีย์ของค่าตั้งระบบทั้งหมด (spec 3.4)
 *
 * ห้ามใช้ string ดิบเรียก SettingService — คีย์ที่พิมพ์ผิดจะกลายเป็นค่า default เงียบ ๆ
 * แล้วใบเสนอราคาจะออกด้วย VAT หรือเกณฑ์อนุมัติที่ผิดโดยไม่มีใครรู้
 */
enum SettingKey: string
{
    // ── ข้อมูลบริษัทสำหรับหัวเอกสาร ──
    case CompanyNameTh = 'company.name_th';
    case CompanyNameEn = 'company.name_en';
    case CompanyTaxId = 'company.tax_id';
    case CompanyBranchCode = 'company.branch_code';
    case CompanyAddressTh = 'company.address_th';
    case CompanyAddressEn = 'company.address_en';
    case CompanyPhone = 'company.phone';
    case CompanyEmail = 'company.email';
    case CompanyWebsite = 'company.website';
    case CompanyLogoPath = 'company.logo_path';
    case CompanySignaturePath = 'company.signature_path';
    case CompanySignerName = 'company.signer_name';
    case CompanySignerPosition = 'company.signer_position';

    // ── ค่าเริ่มต้นของเอกสาร ──
    case VatRate = 'document.vat_rate';
    case QuoteValidDays = 'document.quote_valid_days';
    case PaymentTerms = 'document.payment_terms';
    case DeliveryTerms = 'document.delivery_terms';
    case LeadTimeNote = 'document.lead_time_note';
    case TermsAndConditionsTh = 'document.terms_th';
    case TermsAndConditionsEn = 'document.terms_en';

    // ── เกณฑ์ที่บังคับให้ใบต้องผ่านการอนุมัติก่อนส่ง (spec 4.3) ──
    case ApprovalMaxDiscountPercent = 'approval.max_discount_percent';
    case ApprovalMinMarginPercent = 'approval.min_margin_percent';
    case ApprovalMaxGrandTotal = 'approval.max_grand_total';

    public function group(): string
    {
        return str_contains($this->value, '.')
            ? explode('.', $this->value, 2)[0]
            : 'general';
    }

    public function label(): string
    {
        return match ($this) {
            self::CompanyNameTh => __('ชื่อบริษัท (ไทย)'),
            self::CompanyNameEn => __('ชื่อบริษัท (อังกฤษ)'),
            self::CompanyTaxId => __('เลขประจำตัวผู้เสียภาษี'),
            self::CompanyBranchCode => __('รหัสสาขา'),
            self::CompanyAddressTh => __('ที่อยู่ (ไทย)'),
            self::CompanyAddressEn => __('ที่อยู่ (อังกฤษ)'),
            self::CompanyPhone => __('โทรศัพท์'),
            self::CompanyEmail => __('อีเมล'),
            self::CompanyWebsite => __('เว็บไซต์'),
            self::CompanyLogoPath => __('โลโก้'),
            self::CompanySignaturePath => __('ลายเซ็น'),
            self::CompanySignerName => __('ชื่อผู้ลงนาม'),
            self::CompanySignerPosition => __('ตำแหน่งผู้ลงนาม'),
            self::VatRate => __('อัตรา VAT (%)'),
            self::QuoteValidDays => __('ยืนราคา (วัน)'),
            self::PaymentTerms => __('เงื่อนไขการชำระเงิน'),
            self::DeliveryTerms => __('เงื่อนไขการส่งมอบ'),
            self::LeadTimeNote => __('ระยะเวลาส่งของ'),
            self::TermsAndConditionsTh => __('เงื่อนไขท้ายใบ (ไทย)'),
            self::TermsAndConditionsEn => __('เงื่อนไขท้ายใบ (อังกฤษ)'),
            self::ApprovalMaxDiscountPercent => __('ส่วนลดรวมสูงสุดที่ไม่ต้องอนุมัติ (%)'),
            self::ApprovalMinMarginPercent => __('margin ต่ำสุดที่ไม่ต้องอนุมัติ (%)'),
            self::ApprovalMaxGrandTotal => __('ยอดสุทธิสูงสุดที่ไม่ต้องอนุมัติ (บาท)'),
        };
    }

    /**
     * ค่าที่ระบบใช้เมื่อยังไม่เคยตั้งค่า — ตรงกับตัวเลขในสเปกข้อ 4.3
     */
    public function default(): string|int|null
    {
        return match ($this) {
            self::CompanyNameTh => 'บริษัท เท็กซัน จำกัด',
            self::CompanyNameEn => 'TEXSON Co., Ltd.',
            self::CompanyBranchCode => '00000',
            self::VatRate => '7.00',
            self::QuoteValidDays => 30,
            self::PaymentTerms => 'เครดิต 30 วัน นับจากวันที่ส่งมอบ',
            self::DeliveryTerms => 'ส่งมอบ ณ สถานที่ของลูกค้า (กรุงเทพฯ และปริมณฑล)',
            self::LeadTimeNote => 'ตามที่ระบุในแต่ละรายการ นับจากวันที่ได้รับใบสั่งซื้อ',
            self::ApprovalMaxDiscountPercent => '15.00',
            self::ApprovalMinMarginPercent => '10.00',
            self::ApprovalMaxGrandTotal => '500000.00',
            default => null,
        };
    }

    /**
     * ค่าที่เป็นไฟล์อัปโหลด ไม่ใช่ข้อความ — หน้าตั้งค่าเรนเดอร์คนละแบบ
     */
    public function isFile(): bool
    {
        return in_array($this, [self::CompanyLogoPath, self::CompanySignaturePath], true);
    }

    /**
     * ค่าที่ควรใช้ textarea แทน input บรรทัดเดียว
     */
    public function isMultiline(): bool
    {
        return in_array($this, [
            self::CompanyAddressTh,
            self::CompanyAddressEn,
            self::TermsAndConditionsTh,
            self::TermsAndConditionsEn,
        ], true);
    }

    /**
     * @return array<int, self>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $key): bool => $key->group() === $group,
        ));
    }

    /**
     * กลุ่มทั้งหมดพร้อมชื่อที่แสดงบนหน้าตั้งค่า
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'company' => __('ข้อมูลบริษัท'),
            'document' => __('ค่าเริ่มต้นของเอกสาร'),
            'approval' => __('เกณฑ์การอนุมัติ'),
        ];
    }
}
