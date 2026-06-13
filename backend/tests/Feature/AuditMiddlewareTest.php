<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

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
}
