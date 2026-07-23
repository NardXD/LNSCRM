<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $userEmail,
        public string $loginUrl,
        public string $companyName,
        public ?string $temporaryPassword = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.$this->companyName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }
}
