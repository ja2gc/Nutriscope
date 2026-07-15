<?php

namespace App\Services\Audit\Revisions;

use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditRevisionSnapshot;
use App\Models\AuditRevision;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuditRevisionRegistry
{
    /** @var array<class-string<Model>, AuditRevisionSerializer> */
    private array $serializers = [];

    /** @param iterable<AuditRevisionSerializer> $serializers */
    public function __construct(iterable $serializers)
    {
        foreach ($serializers as $serializer) {
            $type = $serializer->subjectType();
            if (isset($this->serializers[$type])
                || preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $serializer->key()) !== 1
                || $serializer->schemaVersion() < 1
                || ! is_a($type, Model::class, true)) {
                throw new InvalidArgumentException('Invalid or duplicate audit revision serializer.');
            }
            $this->serializers[$type] = $serializer;
        }
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        $serializer = $this->serializerFor($subject::class);
        $snapshot = $serializer->capture($subject);
        $this->assertSnapshot($snapshot);

        return $snapshot;
    }

    public function assertSnapshot(AuditRevisionSnapshot $snapshot): AuditRevisionSerializer
    {
        $serializer = $this->serializerFor($snapshot->subjectType);
        if ($snapshot->serializer !== $serializer->key()
            || $snapshot->schemaVersion !== $serializer->schemaVersion()
            || ! Str::isUuid($snapshot->subjectPublicId)) {
            throw new InvalidArgumentException('Audit revision snapshot does not match its serializer.');
        }
        $this->assertPrivacySafe($snapshot->payload);
        $serializer->present($snapshot->payload);

        return $serializer;
    }

    public function supports(AuditRevision $revision): bool
    {
        $serializer = $this->serializers[$revision->subject_type] ?? null;

        return $serializer !== null && $serializer->schemaVersion() === $revision->schema_version;
    }

    public function keyFor(AuditRevision $revision): string
    {
        return $this->serializerFor($revision->subject_type)->key();
    }

    /** @param array<string, mixed>|null $snapshot */
    public function present(AuditRevision $revision, ?array $snapshot): ?AuditHistorySnapshotDto
    {
        if ($snapshot === null) {
            return null;
        }
        if (! $this->supports($revision)) {
            throw new InvalidArgumentException('Unsupported audit revision serializer version.');
        }
        $this->assertPrivacySafe($snapshot);

        return $this->serializerFor($revision->subject_type)->present($snapshot);
    }

    /** @param class-string<Model>|string $subjectType */
    private function serializerFor(string $subjectType): AuditRevisionSerializer
    {
        return $this->serializers[$subjectType]
            ?? throw new InvalidArgumentException('No audit revision serializer is registered for this record type.');
    }

    /** @param array<string, mixed> $payload */
    private function assertPrivacySafe(array $payload): void
    {
        $prohibited = [
            'patient_display_name', 'patient_name', 'hospital_number', 'date_of_birth', 'medical_diagnosis',
            'screening_result', 'risk_value', 'meal_plan_content', 'file_content', 'ocr_content',
            'ai_prompt', 'ai_output', 'report_content', 'report_parameters', 'raw_response',
        ];
        $walk = function (array $values) use (&$walk, $prohibited): void {
            foreach ($values as $key => $value) {
                $normalized = is_string($key) ? Str::of($key)->snake()->lower()->toString() : '';
                if ($normalized !== '' && in_array($normalized, $prohibited, true)) {
                    throw new InvalidArgumentException('Audit revision snapshot contains a prohibited field.');
                }
                if (is_array($value)) {
                    $walk($value);
                } elseif (is_object($value) || is_resource($value)) {
                    throw new InvalidArgumentException('Audit revision snapshot contains an unsupported value.');
                }
            }
        };
        $walk($payload);
    }
}
