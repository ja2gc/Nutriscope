<?php

namespace Tests\Feature\Audit;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Http\Resources\AuditHistoryResource;
use App\Models\AuditActivity;
use App\Models\AuditRevision;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Audit\AuditEventPresenter;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class AuditHistoricalRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rnd;

    private Recipe $recipe;

    private AuditRevisionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->rnd = User::factory()->rnd()->create();
        $this->recipe = Recipe::factory()->for($this->rnd, 'rnd')->create(['name' => 'Original Recipe']);
        $this->registry = new AuditRevisionRegistry([new FrameworkRecipeRevisionSerializer]);
        $this->app->instance(AuditRevisionRegistry::class, $this->registry);
    }

    public function test_registered_serializer_writes_one_transactional_immutable_revision(): void
    {
        $activity = $this->activity();
        $before = $this->registry->capture($this->recipe);
        $this->recipe->name = 'Updated Recipe';
        $after = $this->registry->capture($this->recipe);
        $writer = $this->app->make(AuditRevisionWriter::class);

        $revision = DB::transaction(fn (): AuditRevision => $writer->write($activity, $before, $after));

        $this->assertSame('Original Recipe', $revision->before['title']);
        $this->assertSame('Updated Recipe', $revision->after['title']);
        $this->assertSame(1, $revision->schema_version);
        $this->assertSame($activity->public_id, $revision->auditEvent->public_id);
    }

    public function test_writer_failure_rolls_back_the_business_mutation(): void
    {
        $activity = $this->activity();
        $before = $this->registry->capture($this->recipe);
        $mismatched = new AuditRevisionSnapshot(
            serializer: 'framework_recipe',
            subjectType: Recipe::class,
            subjectPublicId: fake()->uuid(),
            schemaVersion: 1,
            payload: ['title' => 'Wrong record', 'reference' => fake()->uuid()],
        );

        try {
            DB::transaction(function () use ($activity, $before, $mismatched): void {
                $this->recipe->update(['name' => 'MUST-ROLL-BACK']);
                $this->app->make(AuditRevisionWriter::class)->write($activity, $before, $mismatched);
            });
            $this->fail('Mismatched revision unexpectedly succeeded.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Audit revision snapshots must describe the same record.', $exception->getMessage());
        }

        $this->assertSame('Original Recipe', $this->recipe->fresh()->name);
        $this->assertDatabaseMissing('audit_revisions', ['activity_id' => $activity->id]);
    }

    public function test_registry_rejects_unregistered_and_privacy_unsafe_snapshots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit revision snapshot contains a prohibited field.');
        (new AuditRevisionRegistry([new UnsafeRecipeRevisionSerializer]))->capture($this->recipe);
    }

    public function test_history_route_is_admin_only_uses_event_uuid_and_never_returns_raw_revision_json(): void
    {
        $activity = $this->activity();
        $before = $this->registry->capture($this->recipe);
        $this->recipe->name = 'Updated Recipe';
        $after = $this->registry->capture($this->recipe);
        $after = new AuditRevisionSnapshot(
            serializer: $after->serializer,
            subjectType: $after->subjectType,
            subjectPublicId: $after->subjectPublicId,
            schemaVersion: $after->schemaVersion,
            payload: [...$after->payload, 'internal_secret' => 'RAW-REVISION-SENTINEL'],
        );
        $revision = DB::transaction(
            fn (): AuditRevision => $this->app->make(AuditRevisionWriter::class)->write($activity, $before, $after),
        );
        Recipe::withoutEvents(fn (): ?bool => $this->recipe->delete());

        $this->getJson("/api/admin/audit-logs/{$activity->public_id}/history")->assertUnauthorized();
        $this->actingAs($this->rnd)->getJson("/api/admin/audit-logs/{$activity->public_id}/history")->assertForbidden();
        $this->actingAs($this->admin)->getJson("/api/admin/audit-logs/{$revision->public_id}/history")->assertNotFound();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertOk()
            ->assertJsonPath('data.id', $revision->public_id)
            ->assertJsonPath('data.event.id', $activity->public_id)
            ->assertJsonPath('data.version.serializer', 'framework_recipe')
            ->assertJsonPath('data.version.schema_version', 1)
            ->assertJsonPath('data.before.type', 'framework_recipe')
            ->assertJsonPath('data.before.fields.0.value.value', 'Original Recipe')
            ->assertJsonPath('data.after.fields.0.value.value', 'Updated Recipe')
            ->assertJsonPath('data.event.current_record_url', null)
            ->assertJsonPath('data.read_only', true)
            ->assertJsonMissing(['internal_secret'])
            ->assertJsonMissing(['RAW-REVISION-SENTINEL']);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertStringNotContainsString('RAW-REVISION-SENTINEL', $response->getContent());
        $this->assertLessThanOrEqual(4, $queryCount, 'History detail must have a bounded lookup count.');
    }

    public function test_history_resource_refuses_a_raw_revision_model(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AuditHistoryResource requires a typed audit history record.');

        (new AuditHistoryResource(new AuditRevision))->toArray(Request::create('/'));
    }

    public function test_unregistered_revision_has_no_link_and_cannot_be_opened(): void
    {
        $activity = $this->activity();
        $snapshot = $this->registry->capture($this->recipe);
        DB::transaction(fn (): AuditRevision => $this->app->make(AuditRevisionWriter::class)
            ->write($activity, null, $snapshot));
        $emptyRegistry = new AuditRevisionRegistry([]);
        $this->app->instance(AuditRevisionRegistry::class, $emptyRegistry);
        $activity->load('revision');

        $event = $this->app->make(AuditEventPresenter::class)->present($activity, $this->admin)->toArray();

        $this->assertNull($event['history']);
        $this->actingAs($this->admin)
            ->getJson("/api/admin/audit-logs/{$activity->public_id}/history")
            ->assertNotFound();
    }

    private function activity(): AuditActivity
    {
        return AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'description' => 'Updated recipe',
            'event' => AuditAction::Updated->value,
            'category' => AuditCategory::Operations,
            'domain' => AuditDomain::NutritionLibrary,
            'module' => AuditModule::NutritionCare,
            'subject_type' => $this->recipe->getMorphClass(),
            'subject_id' => $this->recipe->id,
            'subject_public_id' => $this->recipe->uuid,
            'causer_type' => $this->rnd->getMorphClass(),
            'causer_id' => $this->rnd->id,
            'properties' => ['details' => ['changed_fields' => ['ingredients']]],
        ]);
    }
}

class FrameworkRecipeRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'framework_recipe';
    }

    public function subjectType(): string
    {
        return Recipe::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: $this->subjectType(),
            subjectPublicId: (string) $subject->getAttribute('uuid'),
            schemaVersion: $this->schemaVersion(),
            payload: [
                'title' => (string) $subject->getAttribute('name'),
                'reference' => (string) $subject->getAttribute('uuid'),
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        return new AuditHistorySnapshotDto(
            type: $this->key(),
            title: (string) $snapshot['title'],
            reference: (string) $snapshot['reference'],
            fields: [
                new AuditHistoryFieldDto(
                    key: 'name',
                    label: 'Name',
                    value: new AuditValueDto('text', (string) $snapshot['title']),
                ),
            ],
            tables: [],
        );
    }
}

class UnsafeRecipeRevisionSerializer extends FrameworkRecipeRevisionSerializer
{
    public function capture(Model $subject): AuditRevisionSnapshot
    {
        $snapshot = parent::capture($subject);

        return new AuditRevisionSnapshot(
            serializer: $snapshot->serializer,
            subjectType: $snapshot->subjectType,
            subjectPublicId: $snapshot->subjectPublicId,
            schemaVersion: $snapshot->schemaVersion,
            payload: [...$snapshot->payload, 'patient_display_name' => 'FORBIDDEN-PATIENT-NAME'],
        );
    }
}
