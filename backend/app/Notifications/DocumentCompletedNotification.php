<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class DocumentCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Document $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentUrl = config('app.frontend_url') . '/documents/' . $this->document->id;

        return (new MailMessage)
            ->subject('Document Complete: ' . $this->document->title)
            ->greeting('Congratulations!')
            ->line('All parties have signed the document.')
            ->line('**Document:** ' . $this->document->title)
            ->line('**Completed at:** ' . $this->document->completed_at->format('F j, Y \a\t g:i A'))
            ->action('View & Download Document', $documentUrl)
            ->line('You can now download the fully signed document.')
            ->salutation('Thank you for using eSign');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_completed',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'completed_at' => $this->document->completed_at->toIso8601String(),
            'message' => 'All parties have signed ' . $this->document->title,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'document_completed',
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
        ];
    }
}
