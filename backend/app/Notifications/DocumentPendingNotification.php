<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class DocumentPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function via($notifiable): array
    {
        return ['mail', 'broadcast'];
    }

    public function toBroadcast($notifiable): \Illuminate\Notifications\Messages\BroadcastMessage
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'document_id' => $this->document->id,
            'title' => $this->document->title,
            'message' => 'Action Required: ' . $this->document->title,
            'action_url' => '/documents/' . $this->document->id,
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action Required: ' . $this->document->title)
            ->line('A document requires your approval.')
            ->line('Title: ' . $this->document->title)
            ->action('View Document', url('/documents/' . $this->document->id))
            ->line('Thank you for using SADC-eSign.');
    }
}
