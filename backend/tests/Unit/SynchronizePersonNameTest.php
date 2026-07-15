<?php

namespace Tests\Unit;

use App\Actions\Identity\SynchronizePersonName;
use App\Models\User;
use InvalidArgumentException;
use Tests\TestCase;

class SynchronizePersonNameTest extends TestCase
{
    public function test_split_input_is_normalized_and_wins_over_deprecated_name(): void
    {
        $attributes = app(SynchronizePersonName::class)->forCreate([
            'first_name' => '  Maria   Luisa ',
            'last_name' => ' De la   Cruz  ',
            'name' => 'Wrong Deprecated Value',
            'email' => 'maria@example.com',
        ]);

        $this->assertSame('Maria Luisa', $attributes['first_name']);
        $this->assertSame('De la Cruz', $attributes['last_name']);
        $this->assertSame('Maria Luisa De la Cruz', $attributes['name']);
        $this->assertSame('maria@example.com', $attributes['email']);
    }

    public function test_deprecated_only_create_payload_is_left_for_request_validation(): void
    {
        $attributes = ['name' => 'Legacy Client', 'email' => 'legacy@example.com'];

        $this->assertSame($attributes, app(SynchronizePersonName::class)->forCreate($attributes));
    }

    public function test_unrelated_legacy_update_does_not_modify_name_attributes(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Legacy Mononym',
            'first_name' => 'Legacy Mononym',
            'last_name' => null,
        ]);
        $attributes = ['contact_number' => '09170000000'];

        $this->assertSame($attributes, app(SynchronizePersonName::class)->forUpdate($user, $attributes));
    }

    public function test_deliberate_update_can_resolve_an_unchanged_existing_split_part(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $attributes = app(SynchronizePersonName::class)->forUpdate($user, [
            'first_name' => ' Maria Luisa ',
        ]);

        $this->assertSame('Maria Luisa', $attributes['first_name']);
        $this->assertSame('Santos', $attributes['last_name']);
        $this->assertSame('Maria Luisa Santos', $attributes['name']);
    }

    public function test_incomplete_deliberate_split_input_is_rejected_defensively(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Legacy Mononym',
            'first_name' => 'Legacy Mononym',
            'last_name' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A complete first and last name is required.');

        app(SynchronizePersonName::class)->forUpdate($user, ['first_name' => 'Renamed']);
    }

    public function test_explicitly_clearing_one_part_is_not_replaced_from_the_existing_model(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A complete first and last name is required.');

        app(SynchronizePersonName::class)->forUpdate($user, ['last_name' => null]);
    }

    public function test_name_parts_longer_than_the_safe_maximum_are_rejected_defensively(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Names must not exceed 255 characters.');

        app(SynchronizePersonName::class)->forCreate([
            'first_name' => str_repeat('a', 256),
            'last_name' => 'Santos',
        ]);
    }

    public function test_maximum_length_is_applied_after_whitespace_normalization(): void
    {
        $attributes = app(SynchronizePersonName::class)->forCreate([
            'first_name' => 'Maria'.str_repeat(' ', 256).'Luisa',
            'last_name' => 'Santos',
        ]);

        $this->assertSame('Maria Luisa', $attributes['first_name']);
        $this->assertSame('Maria Luisa Santos', $attributes['name']);
    }
}
