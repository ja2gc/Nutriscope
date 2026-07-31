<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $backupUuid) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('NutriScope backup needs attention')
            ->line('A database backup could not be completed.')
            ->line('Backup reference: '.$this->backupUuid)
            ->action('Open Backup & Recovery', rtrim(config('app.frontend_url'), '/').'/admin/backups');
    }
}
