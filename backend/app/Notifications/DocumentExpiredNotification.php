<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class DocumentExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Document $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Document Expired: ' . $this->document->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your document "' . $this->document->title . '" has expired and is no longer available for signing.')
            ->line('If you wish to collect signatures, please upload a new copy of the document.')
            ->salutation('Thank you');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_expired',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'message' => 'Your document "' . $this->document->title . '" has expired.',
        ];
    }
}
