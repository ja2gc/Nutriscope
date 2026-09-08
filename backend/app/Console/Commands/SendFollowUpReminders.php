<?php

namespace App\Console\Commands;

use App\Models\NcpAppointment;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Notify the owning RND one day before a scheduled appointment.
 * Idempotent per appointment, so rerunning the command cannot duplicate alerts.
 */
class SendFollowUpReminders extends Command
{
    protected $signature = 'notifications:follow-up-reminders';

    protected $description = 'Notify RNDs of follow-ups scheduled for tomorrow.';

    public function handle(NotificationService $notifications): int
    {
        $tomorrow = Carbon::tomorrow();

        $appointments = NcpAppointment::query()
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', $tomorrow->toDateString())
            ->with('patient')
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            $alreadySent = Notification::query()
                ->where('type', 'appointment_due')
                ->where('source_module', 'ncp_appointment')
                ->where('source_id', $appointment->id)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $name = $appointment->patient?->display_name ?? 'a patient';
            $when = $appointment->scheduled_at?->format('M j, Y g:i A') ?? $tomorrow->toDateString();
            $notifications->notify(
                [$appointment->rnd_user_id],
                "Appointment due: {$name}",
                "{$name} — {$when} — {$appointment->purpose}",
                'appointment_due',
                'ncp_appointment',
                $appointment->id,
            );

            $sent++;
        }

        $this->info("Follow-up reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
