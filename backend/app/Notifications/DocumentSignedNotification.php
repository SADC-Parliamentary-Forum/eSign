<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;
use App\Models\DocumentSigner;

class DocumentSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Document $document;
    protected DocumentSigner $signer;
    protected bool $declined;
    protected ?string $reason;

    public function __construct(
        Document $document,
        DocumentSigner $signer,
        bool $declined = false,
        ?string $reason = null
    ) {
        $this->document = $document;
        $this->signer = $signer;
        $this->declined = $declined;
        $this->reason = $reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentUrl = config('app.frontend_url') . '/documents/' . $this->document->id;

        if ($this->declined) {
            return (new MailMessage)
                ->subject('Document Declined: ' . $this->document->title)
                ->greeting('Hello,')
                ->line($this->signer->name . ' has declined to sign your document.')
                ->line('**Document:** ' . $this->document->title)
                ->line($this->reason ? '**Reason:** ' . $this->reason : '')
                ->action('View Document', $documentUrl)
                ->salutation('eSign');
        }

        return (new MailMessage)
            ->subject('Document Signed: ' . $this->document->title)
            ->greeting('Hello,')
            ->line($this->signer->name . ' has signed your document.')
            ->line('**Document:** ' . $this->document->title)
            ->action('View Document', $documentUrl)
            ->salutation('eSign');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->declined ? 'document_declined' : 'document_signed',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'signer_name' => $this->signer->name,
            'signer_email' => $this->signer->email,
            'reason' => $this->reason,
            'message' => $this->declined
                ? $this->signer->name . ' declined to sign ' . $this->document->title
                : $this->signer->name . ' signed ' . $this->document->title,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => $this->declined ? 'document_declined' : 'document_signed',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'signer_name' => $this->signer->name,
        ];
    }
}
