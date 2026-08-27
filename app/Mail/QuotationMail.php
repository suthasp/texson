<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Quotation;
use App\Services\QuotationPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ส่งใบเสนอราคาให้ลูกค้าพร้อมไฟล์ PDF แนบ (spec 10 เฟส 3)
 */
class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        /** ภาษาของไฟล์ PDF ที่แนบ — ตั้งชื่อไม่ให้ชนกับ $locale ของ Mailable เอง */
        public readonly string $pdfLocale = 'th',
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('ใบเสนอราคา :no จาก :company', [
                'no' => $this->quotation->displayNo(),
                'company' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quotation',
            with: [
                'quotation' => $this->quotation,
                'note' => $this->note,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $service = app(QuotationPdfService::class);

        return [
            Attachment::fromData(
                fn (): string => $service->render($this->quotation, $this->pdfLocale)->output(),
                $service->filename($this->quotation, $this->pdfLocale),
            )->withMime('application/pdf'),
        ];
    }
}
