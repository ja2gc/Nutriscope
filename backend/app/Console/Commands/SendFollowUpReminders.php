<?php

namespace App\Console\Commands;

use App\Models\Intervention;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Trigger B (rnd.md §7) — notify the owning RND one day before a scheduled
 * follow-up. The follow-up date is intervention.next_followup_date, the same
 * field surfaced as the patient's "next follow-up" across the app.
 *
 * Idempotent: a follow_up notification for an NCP record is written at most once
 * per day, so re-running the daily schedule never duplicates.
 */
class SendFollowUpReminders extends Command
{
    protected $signature = 'notifications:follow-up-reminders';

    protected $description = 'Notify RNDs of follow-ups scheduled for tomorrow.';

    public function handle(NotificationService $notifications): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $today = Carbon::today()->toDateString();

        $interventions = Intervention::query()
            ->whereDate('next_followup_date', $tomorrow)
            ->with('ncpRecord.patient')
            ->get();

        $sent = 0;

        foreach ($interventions as $intervention) {
            $ncp = $intervention->ncpRecord;
            if (! $ncp || ! $ncp->rnd_user_id) {
                continue;
            }

            $alreadySent = Notification::query()
                ->where('type', 'follow_up')
                ->where('source_module', 'ncp')
                ->where('source_id', $ncp->id)
                ->whereDate('created_at', $today)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $name = $ncp->patient?->name ?? 'a patient';
            $notifications->notify(
                [$ncp->rnd_user_id],
                'Upcoming follow-up',
                "Follow-up for {$name} is scheduled tomorrow ({$tomorrow}).",
                'follow_up',
                'ncp',
                $ncp->id,
            );

            $sent++;
        }

        $this->info("Follow-up reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
