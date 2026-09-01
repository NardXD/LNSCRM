<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StorageQuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $pdfContents,
        public string $fromEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromEmail, 'Loc & Stor 24/7'),
            subject: 'Your storage quote from '.$this->data['facility_label'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.storage-quote',
            with: ['data' => $this->data],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContents, 'storage-quote.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
