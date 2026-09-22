<?php

namespace App\Mail;

use App\Actions\GenerateInvoicePdf;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Invoice $invoice) {}

    /**
     * Balasan klien diarahkan ke email pemilik buku.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} dari {$this->invoice->book->name}",
            replyTo: [new Address($this->invoice->book->user->email, $this->invoice->book->name)],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => app(GenerateInvoicePdf::class)->handle($this->invoice),
                "{$this->invoice->invoice_number}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
