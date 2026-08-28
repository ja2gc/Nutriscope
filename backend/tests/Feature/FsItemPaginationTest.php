<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FsItemPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_item_options_are_searched_and_paginated(): void
    {
        $rnd = User::factory()->rnd()->create();
        FsItem::factory()->count(12)->create(['category' => 'Other']);
        FsItem::factory()->create(['name' => 'Banana', 'category' => 'Fruit']);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/fss/fs-items?search=banana&per_page=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Banana')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/fss/fs-items?per_page=11')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
