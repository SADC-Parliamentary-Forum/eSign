<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifySignerMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;
    public string $signerName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $verificationUrl, string $signerName = 'Signer')
    {
        $this->verificationUrl = $verificationUrl;
        $this->signerName = $signerName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SADC-eSign: Verify Your Email Address',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify_signer',
        );
    }
}
