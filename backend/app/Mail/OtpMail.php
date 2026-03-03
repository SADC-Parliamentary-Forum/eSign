<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $signerEmail;

    /**
     * Create a new message instance.
     */
    public function __construct(string $code, string $signerEmail)
    {
        $this->code = $code;
        $this->signerEmail = $signerEmail;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SADC-eSign: Your Identity Verification Code',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp_code',
        );
    }
}
