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
     * Klien melihat nama buku sebagai pengirim, dan balasannya masuk ke email pemilik buku.
     * Alamat pengirim tetap alamat platform karena hanya alamat itu yang diizinkan server SMTP.
     */
    public function envelope(): Envelope
    {
        $book = $this->invoice->book;

        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} dari {$book->name}",
            from: new Address(config('mail.from.address'), $book->name),
            replyTo: [new Address($book->user->email, $book->name)],
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
