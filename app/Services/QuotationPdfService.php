<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Quotation;
use App\Support\BahtText;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * สร้าง PDF ใบเสนอราคา ภาษาไทย/อังกฤษ (spec 5)
 *
 * เรื่องฟอนต์ (ADR-011): dompdf ไม่มีฟอนต์ไทยติดมาด้วย ถ้าไม่ฝัง Sarabun เอง
 * สระบน–ล่างจะหายและวรรณยุกต์จะลอย ฟอนต์จึงถูก commit ไว้ใน resources/fonts/sarabun
 * และประกาศผ่าน @font-face ที่ชี้ path จริงในเครื่อง (chroot ของ dompdf = base_path)
 */
class QuotationPdfService
{
    /** @var array<int, string> */
    private const SUPPORTED_LOCALES = ['th', 'en'];

    /**
     * ไฟล์ฟอนต์ → [weight, style] ที่ dompdf ใช้จับคู่กับ CSS
     *
     * @var array<string, array{string, string}>
     */
    private const FONT_STYLES = [
        'Regular' => ['normal', 'normal'],
        'Bold' => ['bold', 'normal'],
        'Italic' => ['normal', 'italic'],
        'BoldItalic' => ['bold', 'italic'],
    ];

    public function __construct(private readonly SettingService $settings) {}

    public function render(Quotation $quotation, string $locale = 'th'): PdfWrapper
    {
        $locale = in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'th';
        $previous = App::getLocale();

        App::setLocale($locale);

        try {
            $pdf = Pdf::loadView('quotations.pdf', $this->data($quotation, $locale))
                ->setPaper('a4')
                ->setOption('isRemoteEnabled', false)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('defaultFont', 'sarabun');

            $this->registerSarabun($pdf);
        } finally {
            App::setLocale($previous);
        }

        return $pdf;
    }

    /**
     * ลงทะเบียนฟอนต์ Sarabun กับ dompdf โดยตรง
     *
     * ทำแบบนี้แทนการประกาศ @font-face ใน CSS เพราะการ resolve path ของ @font-face
     * ขึ้นกับ base path ของเอกสาร ซึ่งไม่มีเมื่อโหลด HTML จาก view — บนวินโดวส์จะพลาด
     * แล้ว dompdf จะ fallback ไปฟอนต์ละติน ทำให้สระบน–ล่างของภาษาไทยหายไปทั้งหน้า
     */
    private function registerSarabun(PdfWrapper $pdf): void
    {
        $metrics = $pdf->getDomPDF()->getFontMetrics();
        $dir = $this->fontDirectory();

        foreach (self::FONT_STYLES as $file => $style) {
            $path = $dir.'/Sarabun-'.$file.'.ttf';

            if (! is_file($path)) {
                continue;
            }

            $metrics->registerFont(
                ['family' => 'sarabun', 'weight' => $style[0], 'style' => $style[1]],
                'file://'.$path,
            );
        }
    }

    /**
     * ชื่อไฟล์ตามสเปกข้อ 5 — QT-202608-0007_rev1.pdf
     */
    public function filename(Quotation $quotation, string $locale = 'th'): string
    {
        $suffix = $locale === 'en' ? '_EN' : '';

        return $quotation->displayNo().$suffix.'.pdf';
    }

    /**
     * ข้อมูลทั้งหมดที่เทมเพลต PDF ต้องใช้
     *
     * @return array<string, mixed>
     */
    public function data(Quotation $quotation, string $locale): array
    {
        $quotation->loadMissing(['items', 'customer', 'contact', 'site', 'salesUser', 'approver']);

        $isThai = $locale === 'th';

        return [
            'quotation' => $quotation,
            'locale' => $locale,
            'isThai' => $isThai,
            'company' => $this->companyProfile($isThai),
            'fontDir' => $this->fontDirectory(),
            'withholding' => $quotation->withholding(),
            // ยอดเป็นตัวอักษรมีเฉพาะฉบับภาษาไทย ฉบับอังกฤษใช้ตัวเลขอย่างเดียว
            'amountInWords' => $isThai ? BahtText::convert((string) $quotation->grand_total) : null,
            'terms' => $this->terms($quotation, $isThai),
        ];
    }

    /**
     * ข้อมูลบริษัทสำหรับหัวเอกสาร — ดึงจากตาราง settings ให้แก้ได้โดยไม่ต้อง deploy (ADR-004)
     *
     * @return array<string, string|null>
     */
    private function companyProfile(bool $isThai): array
    {
        return [
            'name' => $isThai
                ? $this->settings->string(SettingKey::CompanyNameTh)
                : ($this->settings->string(SettingKey::CompanyNameEn) ?: $this->settings->string(SettingKey::CompanyNameTh)),
            'address' => $isThai
                ? $this->settings->string(SettingKey::CompanyAddressTh)
                : ($this->settings->string(SettingKey::CompanyAddressEn) ?: $this->settings->string(SettingKey::CompanyAddressTh)),
            'tax_id' => $this->settings->string(SettingKey::CompanyTaxId),
            'branch_code' => $this->settings->string(SettingKey::CompanyBranchCode),
            'phone' => $this->settings->string(SettingKey::CompanyPhone),
            'email' => $this->settings->string(SettingKey::CompanyEmail),
            'website' => $this->settings->string(SettingKey::CompanyWebsite),
            'signer_name' => $this->settings->string(SettingKey::CompanySignerName),
            'signer_position' => $this->settings->string(SettingKey::CompanySignerPosition),
            'logo' => $this->imageData($this->settings->string(SettingKey::CompanyLogoPath)) ?? $this->bundledLogo(),
            'signature' => $this->imageData($this->settings->string(SettingKey::CompanySignaturePath)),
        ];
    }

    /**
     * เงื่อนไขท้ายใบ — ใช้ของใบนั้นก่อน ถ้าไม่มีค่อยตกไปที่ค่าตั้งระบบตามภาษา
     */
    private function terms(Quotation $quotation, bool $isThai): ?string
    {
        if (filled($quotation->terms_and_conditions)) {
            return $quotation->terms_and_conditions;
        }

        $fallback = $isThai ? SettingKey::TermsAndConditionsTh : SettingKey::TermsAndConditionsEn;
        $text = $this->settings->string($fallback);

        return $text !== '' ? $text : null;
    }

    /**
     * แปลงรูปที่อัปโหลดไว้เป็น data URI
     *
     * ทำแบบนี้เพราะไฟล์อยู่ใน storage/app/private ซึ่งเข้าถึงผ่าน URL ไม่ได้
     * และ dompdf ปิด isRemoteEnabled ไว้ตามข้อกำหนดความปลอดภัย
     */
    private function imageData(?string $path): ?string
    {
        if (blank($path) || ! Storage::disk('private')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('private')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('private')->get($path));
    }

    /**
     * โลโก้ที่มากับโปรเจกต์ ใช้เมื่อผู้ดูแลระบบยังไม่ได้อัปโหลดของตัวเอง
     *
     * สเปกข้อ 5 บังคับให้ใบเสนอราคามีโลโก้ ปล่อยให้หัวใบว่างไม่ได้ตั้งแต่วันแรก
     * ใช้ฉบับสำหรับพื้นสว่าง เพราะพื้นกระดาษเป็นสีขาว
     */
    private function bundledLogo(): ?string
    {
        $path = public_path('logo/logo-light.png');

        return is_file($path)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
            : null;
    }

    /**
     * โฟลเดอร์ฟอนต์แบบ path ที่ dompdf อ่านได้ (ใช้ / เสมอ แม้บน Windows)
     */
    private function fontDirectory(): string
    {
        return str_replace('\\', '/', resource_path('fonts/sarabun'));
    }
}
