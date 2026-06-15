<?php

namespace Tests\Feature;

use App\Models\CleaningLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CleaningLogTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_fss_can_list_cleaning_logs(): void
    {
        CleaningLog::factory()->count(3)->create(['fss_user_id' => $this->fss->id]);

        $response = $this->actingAs($this->fss)->getJson('/api/fss/cleaning-logs');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'item_name', 'category', 'status', 'cleaned_at']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_fss_can_create_cleaning_log(): void
    {
        $response = $this->actingAs($this->fss)->postJson('/api/fss/cleaning-logs', [
            'item_name'  => 'Walk-in chiller',
            'category'   => 'equipment',
            'status'     => 'done',
            'notes'      => 'Sanitized shelves',
            'cleaned_at' => '2026-06-15 08:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item_name', 'Walk-in chiller');

        $this->assertDatabaseHas('cleaning_logs', [
            'item_name'   => 'Walk-in chiller',
            'fss_user_id' => $this->fss->id,
        ]);
    }

    public function test_create_requires_item_name(): void
    {
        $response = $this->actingAs($this->fss)->postJson('/api/fss/cleaning-logs', [
            'category' => 'equipment',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('item_name');
    }

    public function test_fss_can_update_cleaning_log(): void
    {
        $log = CleaningLog::factory()->create([
            'fss_user_id' => $this->fss->id,
            'status'      => 'pending',
        ]);

        $response = $this->actingAs($this->fss)->patchJson("/api/fss/cleaning-logs/{$log->id}", [
            'status' => 'done',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertDatabaseHas('cleaning_logs', ['id' => $log->id, 'status' => 'done']);
    }

    public function test_fss_can_delete_cleaning_log(): void
    {
        $log = CleaningLog::factory()->create(['fss_user_id' => $this->fss->id]);

        $this->actingAs($this->fss)
            ->deleteJson("/api/fss/cleaning-logs/{$log->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cleaning_logs', ['id' => $log->id]);
    }

    public function test_guest_cannot_access_cleaning_logs(): void
    {
        $this->getJson('/api/fss/cleaning-logs')->assertUnauthorized();
    }
}
