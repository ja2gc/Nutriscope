<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActiveInterventionPlanContractTest extends TestCase
{
    public function test_legacy_revision_runtime_classes_are_retired(): void
    {
        $this->assertFileDoesNotExist(app_path('Services/InterventionRevisionService.php'));
        $this->assertFileDoesNotExist(app_path('Models/InterventionRevision.php'));
        $this->assertFileDoesNotExist(database_path('factories/InterventionRevisionFactory.php'));
    }

    public function test_active_writers_never_assign_legacy_revision_foreign_keys(): void
    {
        foreach ([
            app_path('Http/Controllers/RND/MealPlanController.php'),
            database_path('seeders/PatientSeeder.php'),
        ] as $path) {
            $this->assertStringNotContainsString("'intervention_revision_id' =>", file_get_contents($path), $path);
        }

        $this->assertStringNotContainsString('intervention_revision_id', file_get_contents(app_path('Models/MealPlan.php')));
        $this->assertStringNotContainsString('intervention_revision_id', file_get_contents(app_path('Models/Monitoring.php')));
    }

    public function test_only_plural_intervention_plan_routes_are_active(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

        $this->assertContains('GET|HEAD api/rnd/ncp-records/{ncpRecord}/interventions', $routes);
        $this->assertContains('POST api/rnd/ncp-records/{ncpRecord}/interventions', $routes);
        $this->assertContains('POST api/rnd/ncp-records/{ncpRecord}/interventions/autofill', $routes);
        $this->assertContains('GET|HEAD api/rnd/ncp-records/{ncpRecord}/interventions/recommendations', $routes);

        $this->assertNotContains('GET|HEAD api/rnd/ncp-records/{ncpRecord}/intervention', $routes);
        $this->assertNotContains('POST api/rnd/ncp-records/{ncpRecord}/intervention', $routes);
        $this->assertNotContains('PATCH api/rnd/ncp-records/{ncpRecord}/intervention', $routes);
        $this->assertNotContains('POST api/rnd/ncp-records/{ncpRecord}/intervention/autofill', $routes);
        $this->assertNotContains('GET|HEAD api/rnd/ncp-records/{ncpRecord}/intervention/recommendations', $routes);
    }
}
