<?php

namespace App\Services\Audit;

use App\Models\AuditActivity;
use App\Models\NcpRecord;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class AuditPseudonymousReference
{
    public function resolve(?Model $subject, ?int $ncpRecordId): ?string
    {
        if ($ncpRecordId === null || $ncpRecordId < 1) {
            return null;
        }

        $publicId = $subject instanceof NcpRecord && (int) $subject->getKey() === $ncpRecordId
            ? $subject->getAttribute('uuid')
            : NcpRecord::query()->whereKey($ncpRecordId)->value('uuid');
        if (! is_string($publicId) || ! Uuid::isValid($publicId)) {
            $publicId = $this->historicalPublicId($ncpRecordId);
        }
        if (! is_string($publicId) || ! Uuid::isValid($publicId)) {
            return null;
        }

        return 'NCP-'.strtoupper(substr(hash('sha256', strtolower($publicId)), 0, 16));
    }

    private function historicalPublicId(int $ncpRecordId): ?string
    {
        $ncpType = (new NcpRecord)->getMorphClass();
        $events = AuditActivity::query()
            ->where('ncp_record_id', $ncpRecordId)
            ->latest('id')
            ->limit(20)
            ->get(['subject_type', 'subject_public_id', 'context_type', 'context_public_id']);

        foreach ($events as $event) {
            foreach ([
                [$event->subject_type, $event->subject_public_id],
                [$event->context_type, $event->context_public_id],
            ] as [$type, $publicId]) {
                if ($type === $ncpType && is_string($publicId) && Uuid::isValid($publicId)) {
                    return $publicId;
                }
            }
        }

        return null;
    }
}
