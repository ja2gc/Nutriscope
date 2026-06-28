<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecoveryEmailVerification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your NutriScope recovery email')
            ->greeting('NutriScope recovery email verification')
            ->line('Use this verification code to confirm your recovery email:')
            ->line($this->code)
            ->line('This code expires in 10 minutes.');
    }
}
