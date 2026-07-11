<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use App\Models\AuditActivity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AuditLogger
{
    public function __construct(
        private readonly AuditSanitizer $sanitizer,
        private readonly AuditContextResolver $contextResolver,
        private readonly Request $request,
    ) {}

    public function record(
        AuditAction $action,
        AuditCategory $category,
        AuditDomain $domain,
        ?Model $subject = null,
        ?Model $context = null,
        AuditOutcome $outcome = AuditOutcome::Success,
        AuditSeverity $severity = AuditSeverity::Info,
        array $details = [],
        ?Authenticatable $actor = null,
        ?string $systemActor = null,
    ): AuditActivity {
        return $this->recordEvent(
            $action->value,
            $action,
            $category,
            $domain,
            $subject,
            $context,
            $outcome,
            $severity,
            $details,
            $actor,
            $systemActor,
        );
    }

    public function recordLegacyLogin(array $details, Authenticatable $actor): AuditActivity
    {
        return $this->recordEvent(
            'login',
            AuditAction::LoginSucceeded,
            AuditCategory::Security,
            AuditDomain::Accounts,
            details: $details,
            actor: $actor,
        );
    }

    private function recordEvent(
        string $event,
        AuditAction $action,
        AuditCategory $category,
        AuditDomain $domain,
        ?Model $subject = null,
        ?Model $context = null,
        AuditOutcome $outcome = AuditOutcome::Success,
        AuditSeverity $severity = AuditSeverity::Info,
        array $details = [],
        ?Authenticatable $actor = null,
        ?string $systemActor = null,
    ): AuditActivity {
        if ($actor !== null && $systemActor !== null) {
            throw new InvalidArgumentException('An audit event cannot have both a user actor and a system actor.');
        }

        if ($systemActor !== null) {
            $systemActor = trim($this->sanitizer->text($systemActor) ?? '');

            if ($systemActor === '') {
                throw new InvalidArgumentException('The system actor must not be blank.');
            }
        }

        $resolvedActor = $actor ?? ($systemActor === null ? Auth::user() : null);
        if ($resolvedActor !== null && ! $resolvedActor instanceof Model) {
            throw new InvalidArgumentException('The audit actor must be an Eloquent model.');
        }

        $resolvedContext = $this->contextResolver->resolve($subject, $context);
        $safeDetails = $this->sanitizer->details($details, $category);
        $requestProperties = $this->sanitizer->request($this->request);
        $properties = [
            'actor' => $this->sanitizer->actor($resolvedActor, $systemActor),
            'details' => $safeDetails,
            'request' => $requestProperties,
            ...array_diff_key($safeDetails, array_flip(['actor', 'details', 'request'])),
            'ip' => $requestProperties['ip'],
            'user_agent' => $requestProperties['user_agent'],
        ];

        $logger = activity(config('audit.log_name'))
            ->event($event)
            ->withProperties($properties)
            ->tap(function (AuditActivity $activity) use ($category, $domain, $outcome, $severity, $resolvedContext): void {
                $activity->category = $category;
                $activity->domain = $domain;
                $activity->outcome = $outcome;
                $activity->severity = $severity;

                if ($resolvedContext !== null) {
                    $activity->context_type = $resolvedContext->getMorphClass();
                    $activity->context_id = $resolvedContext->getKey();
                }
            });

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($resolvedActor !== null) {
            $logger->causedBy($resolvedActor);
        } else {
            $logger->causedByAnonymous();
        }

        /** @var AuditActivity $activity */
        $description = $action === AuditAction::Updated
            && $domain === AuditDomain::System
            && isset($safeDetails['access_path'])
            ? 'Accessed '.$this->sanitizer->text((string) $safeDetails['access_path'])
            : $action->label();

        $activity = $logger->log($description);
        $events = $this->request->attributes->get('_audit_events', []);
        $events[] = ['source' => 'explicit'];
        $this->request->attributes->set('_audit_events', $events);

        return $activity;
    }
}
