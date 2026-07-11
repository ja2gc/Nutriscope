<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditMiddleware;
use App\Models\Assessment;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\Inventory;
use App\Models\NcpRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuditMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_middleware_logs_mutations_not_reads()
    {
        $user = User::create([
            'name' => 'Test RND',
            'email' => 'rnd@example.com',
            'password' => bcrypt('password'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Decision B (Spec 5): routine GET reads are NOT access-logged.
        $this->getJson('/api/rnd/patients')->assertStatus(200);
        $accessLogs = fn () => Activity::where('description', 'like', 'Accessed%')->count();
        $this->assertSame(0, $accessLogs());

        // A mutation IS access-logged (even if it fails validation — middleware runs after).
        $this->postJson('/api/rnd/patients', []);

        $this->assertSame(1, $accessLogs());
        $activity = Activity::where('description', 'like', 'Accessed%')->first();
        $this->assertEquals('audit', $activity->log_name);
        $this->assertEquals($user->id, $activity->causer_id);
        $this->assertArrayHasKey('url', $activity->properties);
        $this->assertArrayHasKey('method', $activity->properties);
        $this->assertArrayHasKey('ip', $activity->properties);
        $this->assertEquals('POST', $activity->properties['method']);
    }

    public function test_audit_middleware_does_not_log_unauthenticated()
    {
        $response = $this->getJson('/api/rnd/patients');

        $response->assertStatus(401);

        $this->assertEquals(0, Activity::count());
    }

    public function test_incidental_model_event_does_not_suppress_request_fallback(): void
    {
        $user = User::factory()->rnd()->create();
        $request = Request::create('/api/unrelated-action', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        $this->actingAs($user);

        app(AuditMiddleware::class)->handle($request, function (): Response {
            FsItem::factory()->create();

            return new Response('ok');
        });

        $this->assertSame(
            1,
            Activity::query()->where('description', 'like', 'Accessed%')->count(),
            json_encode([
                'events' => $request->attributes->get('_audit_events'),
                'descriptions' => Activity::query()->pluck('description')->all(),
            ]),
        );
        $this->assertSame(1, Activity::query()->where('subject_type', FsItem::class)->count());
    }

    public function test_equivalent_model_event_suppresses_duplicate_request_fallback(): void
    {
        $user = User::factory()->rnd()->create();
        $request = Request::create('/api/fss/fs-items', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        $this->actingAs($user);

        app(AuditMiddleware::class)->handle($request, function (): Response {
            FsItem::factory()->create();

            return new Response('ok');
        });

        $this->assertSame(0, Activity::query()->where('description', 'like', 'Accessed%')->count());
        $this->assertSame(1, Activity::query()->where('subject_type', FsItem::class)->count());
    }

    public function test_singular_assessment_intervention_and_inventory_routes_do_not_duplicate_model_audits(): void
    {
        $user = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create();
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'weight' => 70]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $inventory = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id, 'unit' => 'kg']);

        $cases = [
            'assessment' => [
                "/api/rnd/ncp-records/{$ncp->uuid}/assessment",
                $assessment,
                fn () => $assessment->update(['weight' => 71]),
            ],
            'intervention' => [
                "/api/rnd/ncp-records/{$ncp->uuid}/intervention",
                $intervention,
                fn () => $intervention->update(['session_type' => 'route-dedupe-test']),
            ],
            'inventory' => [
                "/api/fss/inventory/{$inventory->uuid}",
                $inventory,
                fn () => $inventory->update(['unit' => 'g']),
            ],
        ];

        foreach ($cases as $label => [$path, $subject, $mutation]) {
            Activity::query()->delete();
            $request = Request::create($path, 'PATCH');
            $request->setUserResolver(fn () => $user);
            $this->app->instance('request', $request);
            $this->actingAs($user);

            app(AuditMiddleware::class)->handle($request, function () use ($mutation): Response {
                $mutation();

                return new Response('ok');
            });

            $this->assertSame(1, Activity::query()->where('subject_type', $subject::class)->count(), $label);
            $this->assertSame(0, Activity::query()->where('description', 'like', 'Accessed%')->count(), $label);
        }
    }
}
