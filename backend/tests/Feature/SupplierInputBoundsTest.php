<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInputBoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_profile_text_fields_have_explicit_limits(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        foreach (['category', 'contact', 'address', 'payment_terms'] as $field) {
            $this->actingAs($rnd, 'sanctum')->postJson('/api/fss/suppliers', [
                'name' => 'Supplier '.$field,
                $field => str_repeat('x', 256),
            ])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $supplier = Supplier::query()->create(['name' => 'Existing Supplier']);
        $this->actingAs($rnd, 'sanctum')->patchJson("/api/fss/suppliers/{$supplier->uuid}", [
            'notes' => str_repeat('x', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('notes');
    }
}
