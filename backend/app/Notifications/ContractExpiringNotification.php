<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $contract;

    /**
     * Create a new notification instance.
     */
    public function __construct(Contract $contract)
    {
        $this->contract = $contract;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail']; // Could add 'database' or 'broadcast'
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = now()->diffInDays($this->contract->end_date, false);

        return (new MailMessage)
            ->subject('Action Required: Contract Expiring')
            ->line("The contract '{$this->contract->reference_number}' with {$this->contract->counterparty_name} is expiring in {$daysLeft} days.")
            ->action('View Contract', url('/contracts/' . $this->contract->id))
            ->line('Please review renewal terms.');
    }
}
