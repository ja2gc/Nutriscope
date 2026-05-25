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

    public function test_audit_middleware_logs_request()
    {
        $user = User::create([
            'name' => 'Test RND',
            'email' => 'rnd@example.com',
            'password' => bcrypt('password'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Before request, no activity log
        $this->assertEquals(0, Activity::count());

        // We use patients endpoint since it is covered by audit
        $response = $this->getJson('/api/rnd/patients');

        $response->assertStatus(200);

        // After request, activity log should exist
        $this->assertEquals(1, Activity::count());
        $activity = Activity::first();
        
        $this->assertEquals('audit', $activity->log_name);
        $this->assertEquals($user->id, $activity->causer_id);
        
        // Assert properties
        $this->assertArrayHasKey('url', $activity->properties);
        $this->assertArrayHasKey('method', $activity->properties);
        $this->assertArrayHasKey('ip', $activity->properties);
        $this->assertEquals('GET', $activity->properties['method']);
    }

    public function test_audit_middleware_does_not_log_unauthenticated()
    {
        $response = $this->getJson('/api/rnd/patients');

        $response->assertStatus(401);

        $this->assertEquals(0, Activity::count());
    }
}
