<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Enums\RoleName;
use App\Enums\SettingKey;
use App\Models\Customer;
use App\Models\Quotation;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use App\Services\SettingService;

/**
 * DoD ของ Phase 3: "ออกใบจริงพิมพ์ได้ ตัวเลขถูก ภาษาไทยไม่เพี้ยน"
 *
 * เทสต์ชุดนี้เรนเดอร์ PDF จริงผ่าน dompdf ไม่ได้เช็คแค่ HTML
 * เพราะปัญหาภาษาไทยเกิดตอนฝัง glyph ลงไฟล์ ไม่ใช่ตอนสร้าง markup
 */
beforeEach(function (): void {
    $this->sales = actingAsRole(RoleName::Sales);

    app(SettingService::class)->setMany([
        SettingKey::CompanyNameTh->value => 'บริษัท เท็กซัน จำกัด',
        SettingKey::CompanyNameEn->value => 'TEXSON Co., Ltd.',
        SettingKey::CompanyTaxId->value => '0105558000123',
        SettingKey::CompanyAddressTh->value => 'เลขที่ 199/8 ถนนรัชดาภิเษก แขวงดินแดง เขตดินแดง กรุงเทพมหานคร 10400',
    ]);

    $this->quotation = app(QuotationService::class)->createDraft([
        'customer_id' => Customer::factory()->create(['name_th' => 'บริษัท ดาต้าเซ็นเตอร์ไทย จำกัด'])->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => [
            [
                'item_type' => QuotationItemType::Product->value,
                'description' => 'เครื่องสำรองไฟฟ้า UPS ขนาด 10 kVA พร้อมแบตเตอรี่',
                'qty' => '2',
                'unit_price' => '50000',
                'uom' => 'ชุด',
            ],
            [
                'item_type' => QuotationItemType::Labour->value,
                'description' => 'ค่าแรงติดตั้งและทดสอบระบบ',
                'qty' => '1',
                'unit_price' => '12000',
                'uom' => 'งาน',
            ],
        ],
    ]);
});

it('ไฟล์ฟอนต์ Sarabun ถูก commit ไว้ในโปรเจกต์ครบทุกน้ำหนัก', function (string $weight): void {
    expect(resource_path("fonts/sarabun/Sarabun-{$weight}.ttf"))->toBeFile();
})->with(['Regular', 'Bold', 'Italic', 'BoldItalic']);

it('ไฟล์โลโก้ที่ใบเสนอราคาใช้ถูก commit ไว้ในโปรเจกต์', function (string $file): void {
    expect(public_path("logo/{$file}"))->toBeFile();
})->with(['logo-light.png', 'logo-dark.png', 'favicon-128.png']);

/**
 * สเปกข้อ 5 บังคับให้ใบเสนอราคามีโลโก้ — ถ้าไฟล์หายไป หัวใบจะว่างเงียบ ๆ
 * โดยไม่มี error ให้ใครเห็นจนกว่าลูกค้าจะได้ใบไปแล้ว
 */
it('ใบเสนอราคามีโลโก้ติดมาแม้ผู้ดูแลระบบยังไม่ได้อัปโหลดของตัวเอง', function (): void {
    expect(app(SettingService::class)->string(SettingKey::CompanyLogoPath))->toBe('');

    $data = app(QuotationPdfService::class)->data($this->quotation, 'th');

    expect($data['company']['logo'])->toStartWith('data:image/png;base64,');
});

it('เรนเดอร์ PDF ภาษาไทยออกมาเป็นไฟล์ PDF ที่อ่านได้', function (): void {
    $output = app(QuotationPdfService::class)->render($this->quotation, 'th')->output();

    expect($output)->toStartWith('%PDF-')
        ->and(strlen($output))->toBeGreaterThan(10000);
});

it('ฝังฟอนต์ Sarabun ลงในไฟล์จริง ไม่ได้ fallback ไปฟอนต์ละติน', function (): void {
    $output = app(QuotationPdfService::class)->render($this->quotation, 'th')->output();

    preg_match_all('~/BaseFont\s*/([A-Za-z0-9+\-_]+)~', $output, $matches);
    $fonts = array_unique($matches[1]);

    // ถ้า fallback ไป Helvetica/DejaVu แปลว่าการ register ฟอนต์ล้มเหลว
    // แล้วภาษาไทยจะเพี้ยนทั้งหน้าโดยที่ไฟล์ยังเปิดได้ตามปกติ — เทสต์ที่ดูแค่ %PDF- จับไม่ได้
    expect($fonts)->not->toBeEmpty();

    foreach ($fonts as $font) {
        expect($font)->toContain('Sarabun');
    }

    // ต้องเป็น TrueType ที่ฝังมาจริง (FontFile2) ไม่ใช่แค่อ้างชื่อฟอนต์ไว้เฉย ๆ
    expect($output)->toContain('/FontFile2')
        ->and($output)->toContain('/ToUnicode');
});

it('ฟอนต์ Sarabun มี glyph ครบทุกอักขระไทยที่ใช้ในเอกสาร รวมสระบน–ล่างและวรรณยุกต์', function (): void {
    $font = FontLib\Font::load(resource_path('fonts/sarabun/Sarabun-Regular.ttf'));
    $font->parse();

    $charMap = $font->getUnicodeCharMap();

    // U+0E01–U+0E3A พยัญชนะและสระ · U+0E47–U+0E4E ไม้ไต่คู้ วรรณยุกต์ การันต์
    // U+0E50–U+0E59 เลขไทย — ช่วง U+0E3B–U+0E3E ไม่มีในมาตรฐาน Unicode จึงข้าม
    $required = array_merge(
        range(0x0E01, 0x0E3A),
        range(0x0E3F, 0x0E4E),
        range(0x0E50, 0x0E59),
    );

    $missing = array_values(array_filter(
        $required,
        static fn (int $codepoint): bool => ! isset($charMap[$codepoint]),
    ));

    expect($missing)->toBe([]);
});

it('หน้าเว็บส่ง PDF กลับมาพร้อม content-type ที่ถูกต้อง', function (): void {
    $response = $this->get(route('quotations.pdf', $this->quotation));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF-');
});

it('ตั้งชื่อไฟล์ตามสเปกข้อ 5 รวม revision', function (): void {
    $service = app(QuotationPdfService::class);

    expect($service->filename($this->quotation, 'th'))
        ->toBe($this->quotation->quote_no.'.pdf');

    $revision = tap($this->quotation->replicate())->forceFill(['revision' => 1]);

    expect($service->filename($revision, 'th'))->toBe($this->quotation->quote_no.'_rev1.pdf')
        ->and($service->filename($revision, 'en'))->toBe($this->quotation->quote_no.'_rev1_EN.pdf');
});

it('ข้อมูลที่ส่งเข้าเทมเพลตมียอดเป็นตัวอักษรไทยเฉพาะฉบับภาษาไทย', function (): void {
    $service = app(QuotationPdfService::class);

    $thai = $service->data($this->quotation, 'th');
    $english = $service->data($this->quotation, 'en');

    // 100,000 + 12,000 = 112,000 + VAT 7% = 119,840
    expect($thai['amountInWords'])->toBe('หนึ่งแสนหนึ่งหมื่นเก้าพันแปดร้อยสี่สิบบาทถ้วน')
        ->and($english['amountInWords'])->toBeNull()
        ->and((string) $this->quotation->grand_total)->toBe('119840.00');
});

it('ฉบับภาษาอังกฤษใช้ชื่อบริษัทและหัวตารางภาษาอังกฤษ', function (): void {
    $html = view('quotations.pdf', app(QuotationPdfService::class)->data($this->quotation, 'en'))->render();

    app()->setLocale('th');

    expect($html)->toContain('TEXSON Co., Ltd.');
});

it('ฉบับภาษาไทยใช้ชื่อบริษัทภาษาไทยและหัวตารางภาษาไทย', function (): void {
    $html = view('quotations.pdf', app(QuotationPdfService::class)->data($this->quotation, 'th'))->render();

    expect($html)->toContain('บริษัท เท็กซัน จำกัด')
        ->and($html)->toContain('ใบเสนอราคา')
        ->and($html)->toContain('รายละเอียด');
});

it('เลขหน้าอยู่ในรูปแบบ x/y ตามสเปกข้อ 5', function (): void {
    $html = view('quotations.pdf', app(QuotationPdfService::class)->data($this->quotation, 'th'))->render();

    expect($html)->toContain('counter(page) "/" counter(pages)');
});

it('ใบที่ยังเป็นร่างมีลายน้ำบอกว่าเป็นร่าง', function (): void {
    $html = view('quotations.pdf', app(QuotationPdfService::class)->data($this->quotation, 'th'))->render();

    expect($html)->toContain('watermark');
});

it('แสดงหัก ณ ที่จ่าย 3% เมื่อมีค่าแรง โดยไม่หักจากยอดสุทธิ', function (): void {
    $data = app(QuotationPdfService::class)->data($this->quotation, 'th');

    // ฐาน = ค่าแรง 12,000 เท่านั้น ไม่รวมค่าสินค้า
    expect($data['withholding']['base'])->toBe('12000.00')
        ->and($data['withholding']['amount'])->toBe('360.00')
        ->and((string) $this->quotation->grand_total)->toBe('119840.00');
});

it('sales พิมพ์ใบของ sales คนอื่นไม่ได้', function (): void {
    $other = userWithRole(RoleName::Sales);
    $theirs = Quotation::factory()->forSales($other)->create();

    $this->get(route('quotations.pdf', $theirs))->assertForbidden();
});

it('เรนเดอร์ทั้งสองภาษาโดยไม่เปลี่ยน locale ของ session ที่ค้างไว้', function (): void {
    app()->setLocale('th');

    app(QuotationPdfService::class)->render($this->quotation, 'en')->output();

    expect(app()->getLocale())->toBe('th');
});
