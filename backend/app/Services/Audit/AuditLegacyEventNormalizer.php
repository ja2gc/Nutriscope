<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;

class AuditLegacyEventNormalizer
{
    /** @return array{action: AuditAction|null, subject_label: string|null, remove: bool}|null */
    public static function normalize(string $event, ?string $subjectType = null): ?array
    {
        $event = strtolower(trim($event));
        if (AuditAction::tryFrom($event) !== null) {
            return null;
        }

        return match ($event) {
            'approved' => self::event(AuditAction::Created, 'Approval'),
            'ordered' => self::event(AuditAction::Updated, 'Purchase order ordered'),
            'received' => self::event(AuditAction::Updated, 'Goods received'),
            'reversed' => self::event(AuditAction::Updated, 'Reversal'),
            'adjusted' => self::event(AuditAction::Updated, 'Inventory'),
            'price_corrected' => self::event(AuditAction::Updated, 'Price'),
            'uploaded' => self::event(AuditAction::Created, 'Attachment'),
            'imported' => self::event(AuditAction::Created, 'Food item'),
            'generated' => self::generated($subjectType),
            'completed' => self::event(AuditAction::Updated, 'Status'),
            'login' => self::event(AuditAction::Created, 'Session'),
            'login_succeeded' => self::event(AuditAction::Created, 'Session'),
            'login_failed', 'authentication_failed' => self::event(AuditAction::Failed, 'Login attempt'),
            'rate_limit_exceeded' => self::event(AuditAction::Failed, 'Rate limit exceeded'),
            'logout' => self::event(AuditAction::Deleted, 'Session'),
            'password_changed', 'password_reset' => self::event(AuditAction::Updated, 'Password'),
            'recovery_email_changed', 'recovery_email_verified' => self::event(AuditAction::Updated, 'Recovery email'),
            'appointment_scheduled' => self::event(AuditAction::Created, 'Appointment scheduled'),
            'visit_started' => self::event(AuditAction::Created, 'Visit started'),
            'visit_completed' => self::event(AuditAction::Updated, 'Visit completed'),
            'visit_ended_early' => self::event(AuditAction::Updated, 'Visit ended early'),
            'appointment_cancelled' => self::event(AuditAction::Updated, 'Appointment cancelled'),
            'appointment_no_show' => self::event(AuditAction::Updated, 'Appointment marked no-show'),
            'appointment_rescheduled' => self::event(AuditAction::Updated, 'Appointment rescheduled'),
            'cycle_completed' => self::event(AuditAction::Updated, 'NCP cycle completed'),
            'cycle_discontinued' => self::event(AuditAction::Discontinued, 'NCP cycle discontinued'),
            'account_suspended', 'account_blocked', 'user_blocked' => self::event(AuditAction::Suspended, 'User account'),
            'account_unsuspended', 'account_unblocked', 'user_unblocked' => self::event(AuditAction::Unsuspended, 'User account'),
            'audit_log_viewed', 'exported', 'settings_changed', 'profile_changed',
            'authorization_denied', 'access_denied', 'denied',
            'blocked', 'unblocked', 'ip_blocked', 'ip_unblocked' => ['action' => null, 'subject_label' => null, 'remove' => true],
            default => self::event(AuditAction::Updated, 'Record'),
        };
    }

    /** @return list<string> */
    public static function removedEvents(): array
    {
        return [
            'audit_log_viewed', 'exported', 'settings_changed', 'profile_changed',
            'authorization_denied', 'access_denied', 'denied',
            'blocked', 'unblocked', 'ip_blocked', 'ip_unblocked',
        ];
    }

    /** @return list<string> */
    public static function reportAccessActions(): array
    {
        return ['viewed', 'downloaded', 'archived'];
    }

    /** @return array{action: AuditAction, subject_label: string, remove: false} */
    private static function generated(?string $subjectType): array
    {
        $normalizedType = strtolower(preg_replace('/[^a-z0-9]/i', '', $subjectType ?? '') ?? '');

        return match (true) {
            str_ends_with($normalizedType, 'mealplan') => self::event(AuditAction::Created, 'Meal plan'),
            str_ends_with($normalizedType, 'shoppinglist') => self::event(AuditAction::Created, 'Shopping list'),
            default => self::event(AuditAction::Updated, 'Generated record'),
        };
    }

    /** @return array{action: AuditAction, subject_label: string, remove: false} */
    private static function event(AuditAction $action, string $subjectLabel): array
    {
        return ['action' => $action, 'subject_label' => $subjectLabel, 'remove' => false];
    }
}
