<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ContactLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * แจ้งทีมขายว่ามีคำขอติดต่อใหม่จากหน้าเว็บ
 */
class ContactLeadReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContactLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('คำขอติดต่อใหม่จาก :name', ['name' => $this->lead->name]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-lead', with: ['lead' => $this->lead]);
    }
}
