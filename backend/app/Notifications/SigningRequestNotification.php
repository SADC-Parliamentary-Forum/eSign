<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;
use App\Models\DocumentSigner;

class SigningRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Document $document;
    protected DocumentSigner $signer;

    public function __construct(Document $document, DocumentSigner $signer)
    {
        $this->document = $document;
        $this->signer = $signer;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $signingUrl = $this->signer->getSigningUrl();

        return (new MailMessage)
            ->subject('Document Requires Your Signature: ' . $this->document->title)
            ->greeting('Hello ' . $this->signer->name . ',')
            ->line($this->document->user->name . ' has sent you a document to sign.')
            ->line('**Document:** ' . $this->document->title)
            ->action('Review & Sign Document', $signingUrl)
            ->line('This link is unique to you. Do not share it with others.')
            ->line($this->document->expires_at
                ? 'Please sign before ' . $this->document->expires_at->format('F j, Y') . '.'
                : 'Please sign at your earliest convenience.')
            ->salutation('Thank you');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'signing_request',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'signer_id' => $this->signer->id,
            'from_user' => $this->document->user->name,
            'message' => 'You have a document to sign: ' . $this->document->title,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'signing_request',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'from_user' => $this->document->user->name,
        ];
    }
}
