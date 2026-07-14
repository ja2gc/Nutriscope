<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cleaning-log routes were removed in R2 scope enforcement (replaced by diet-list /
 * accomplishment capture built in commit 93920e6). These tests assert the endpoints
 * are gone so a future route accident is caught immediately.
 */
class CleaningLogTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_cleaning_log_list_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/cleaning-logs')
            ->assertNotFound();
    }

    public function test_cleaning_log_create_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/cleaning-logs', ['item_name' => 'Walk-in chiller'])
            ->assertNotFound();
    }

    public function test_cleaning_log_update_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->patchJson('/api/fss/cleaning-logs/1', ['status' => 'done'])
            ->assertNotFound();
    }

    public function test_cleaning_log_delete_route_is_gone(): void
    {
        $this->actingAs($this->fss)
            ->deleteJson('/api/fss/cleaning-logs/1')
            ->assertNotFound();
    }
}
