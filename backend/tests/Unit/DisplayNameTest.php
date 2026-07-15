<?php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\User;
use App\Support\PersonNameRules;
use Tests\TestCase;

class DisplayNameTest extends TestCase
{
    public function test_user_and_patient_share_the_split_display_contract(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Legacy User',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
        ]);
        $patient = (new Patient)->forceFill([
            'name' => 'Legacy Patient',
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz III',
        ]);

        $this->assertSame('Maria Luisa De la Cruz', $user->display_name);
        $this->assertSame('Juan Miguel Dela Cruz III', $patient->display_name);
    }

    public function test_incomplete_or_blank_split_pair_falls_back_to_exact_legacy_name(): void
    {
        foreach ([
            ['first_name' => 'Legacy Mononym', 'last_name' => null],
            ['first_name' => null, 'last_name' => 'Existing Surname'],
            ['first_name' => '   ', 'last_name' => 'Surname'],
        ] as $split) {
            $user = (new User)->forceFill([
                'name' => '  Exact  Legacy Display  ',
                ...$split,
            ]);

            $this->assertSame('  Exact  Legacy Display  ', $user->display_name);
        }
    }

    public function test_display_name_is_not_appended_to_arbitrary_model_serialization(): void
    {
        $user = (new User)->forceFill([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
        ]);

        $this->assertArrayNotHasKey('display_name', $user->toArray());
    }

    public function test_name_rules_normalize_whitespace_without_splitting_compound_values(): void
    {
        $this->assertSame('Maria Luisa', PersonNameRules::normalize("  Maria \t  Luisa  "));
        $this->assertSame('De la Cruz', PersonNameRules::normalize('  De   la   Cruz '));
        $this->assertNull(PersonNameRules::normalize('   '));
    }

    public function test_name_rules_detect_control_characters_but_allow_name_punctuation(): void
    {
        $this->assertTrue(PersonNameRules::containsControlCharacters("Maria\nSantos"));
        $this->assertTrue(PersonNameRules::containsControlCharacters("Juan\0Cruz"));
        $this->assertFalse(PersonNameRules::containsControlCharacters("O'Connor-Santos"));
        $this->assertSame(255, PersonNameRules::MAX_LENGTH);
    }
}
