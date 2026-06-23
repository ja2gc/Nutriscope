<?php

namespace Tests\Feature;

use App\Models\Sop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SopFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_can_save_a_revision_and_it_becomes_current(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd)
            ->postJson('/api/sop', ['title' => 'Kitchen SOP', 'body' => 'Wash hands.'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Kitchen SOP');

        $this->actingAs($rnd)->getJson('/api/sop')
            ->assertOk()
            ->assertJsonPath('data.body', 'Wash hands.');
    }

    public function test_saving_is_append_only_and_history_keeps_every_version(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd)->postJson('/api/sop', ['title' => 'v1', 'body' => 'first']);
        $this->actingAs($rnd)->postJson('/api/sop', ['title' => 'v2', 'body' => 'second']);

        $this->assertSame(2, Sop::count());

        // Current = latest.
        $this->actingAs($rnd)->getJson('/api/sop')->assertJsonPath('data.title', 'v2');

        // History = all versions, newest first.
        $this->actingAs($rnd)->getJson('/api/sop/history')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'v2')
            ->assertJsonPath('data.1.title', 'v1');
    }

    public function test_fss_can_read_but_cannot_author(): void
    {
        $fss = User::factory()->fss()->create();
        Sop::create(['title' => 'Posted', 'body' => 'body', 'created_by' => User::factory()->create(['role' => 'RND'])->id]);

        $this->actingAs($fss)->getJson('/api/sop')->assertOk()->assertJsonPath('data.title', 'Posted');
        $this->actingAs($fss)->postJson('/api/sop', ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_current_is_null_when_no_sop_set(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd)->getJson('/api/sop')->assertOk()->assertExactJson(['data' => null]);
    }
}
