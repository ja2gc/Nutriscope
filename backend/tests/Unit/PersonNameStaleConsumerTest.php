<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PersonNameStaleConsumerTest extends TestCase
{
    public function test_direct_person_name_reads_are_limited_to_explicit_legacy_audit_compatibility(): void
    {
        $actual = $this->matchingProductionLines(
            '/(?:\$(?:[A-Za-z_][A-Za-z0-9_]*(?:user|patient|actor|causer|rnd|admin|fss|preparer|creator|clinician|author|owner|createdBy|updatedBy)|user|patient|actor|causer|rnd|admin|fss|preparer|creator|clinician|author|owner|createdBy|updatedBy)|->(?:[A-Za-z_][A-Za-z0-9_]*(?:user|patient|actor|causer|rnd|admin|fss|preparer|creator|clinician|author|owner|createdBy|updatedBy)|user|patient|actor|causer|rnd|admin|fss|preparer|creator|clinician|author|owner|createdBy|updatedBy))(?:\?->|->)name\b/i',
        );

        $expected = [
            "app/Http/Controllers/Admin/UserController.php:'name' => \$user->name,",
            "app/Http/Controllers/Auth/AuthController.php:'name' => \$user->name,",
            "app/Services/Audit/AuditOversightBackfill.php:\$displayName = \$parts !== [] ? implode(' ', \$parts) : trim((string) \$patient->name);",
        ];

        sort($expected);
        $this->assertSame($expected, $actual,
            'A direct person name read was added outside the explicit legacy audit compatibility boundaries.');
    }

    public function test_person_queries_keep_only_deliberate_legacy_fallbacks_and_complete_projections(): void
    {
        $users = file_get_contents(app_path('Http/Controllers/Admin/UserController.php'));
        $patients = file_get_contents(app_path('Http/Controllers/RND/PatientController.php'));

        $this->assertSame(1, substr_count($users, "COALESCE(NULLIF(last_name, ''), name)"));
        $this->assertSame(1, substr_count($users, "COALESCE(NULLIF(first_name, ''), name)"));
        $this->assertStringContainsString('RankedSearch::apply($query', $patients);
        $this->assertStringContainsString("'name', 'first_name', 'last_name', 'physician', 'ward', 'hospital_number'", $patients);

        preg_match_all("/(?:user|rnd):id,uuid,([^']+)/", $patients, $projections);
        $this->assertNotEmpty($projections[1]);
        foreach ($projections[1] as $projection) {
            $this->assertStringContainsString('name', $projection);
            $this->assertStringContainsString('first_name', $projection);
            $this->assertStringContainsString('last_name', $projection);
        }
    }

    public function test_unrelated_named_entities_remain_outside_the_person_name_contract(): void
    {
        foreach ([
            'FoodItem.php',
            'FoodServiceRecipe.php',
            'FsItem.php',
            'MenuCycle.php',
            'MenuCycleTemplate.php',
            'Recipe.php',
            'ReportTemplate.php',
            'ShoppingList.php',
            'Supplier.php',
        ] as $model) {
            $source = file_get_contents(app_path("Models/{$model}"));

            $this->assertStringContainsString("'name'", $source, "{$model} must retain its entity name.");
            $this->assertStringNotContainsString('HasDisplayName', $source, "{$model} is not a person.");
            $this->assertStringNotContainsString('first_name', $source, "{$model} is not a person.");
            $this->assertStringNotContainsString('last_name', $source, "{$model} is not a person.");
        }
    }

    /** @return list<string> */
    private function matchingProductionLines(string $pattern): array
    {
        $matches = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $code = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", "''", $line) ?? $line;
                if (preg_match($pattern, $code) === 1) {
                    $matches[] = $relative.':'.trim($line);
                }
            }
        }

        sort($matches);

        return $matches;
    }
}
