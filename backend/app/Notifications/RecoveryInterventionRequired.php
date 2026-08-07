<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecoveryInterventionRequired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $recoveryUuid, private readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NutriScope recovery needs attention')
            ->line('Recovery request '.$this->recoveryUuid.' needs attention.')
            ->line($this->reason)
            ->line('Open the Admin Backup page to review the current status.');
    }
}
