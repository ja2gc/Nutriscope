<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_scoped_and_paginated_at_ten(): void
    {
        $user = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        Notification::factory()->count(12)->for($user)->create();
        Notification::factory()->count(3)->for($other)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?per_page=11')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
