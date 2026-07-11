<?php

namespace Tests\Feature\Audit;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuditRouteCoverageTest extends TestCase
{
    public function test_every_unsafe_route_has_an_audit_classification_and_reason(): void
    {
        $routeList = new Process(
            [PHP_BINARY, base_path('artisan'), 'route:list', '--json'],
            base_path(),
            ['APP_ENV' => 'local'],
        );
        $routeList->mustRun();
        $routes = json_decode($routeList->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $actualUnsafeRoutes = collect($routes)
            ->filter(fn (array $route) => collect(explode('|', $route['method']))
                ->contains(fn (string $method) => ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)))
            ->map(fn (array $route) => "{$route['method']} {$route['uri']}")
            ->sort()
            ->values()
            ->all();

        $coverage = [
            'POST _boost/browser-logs' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Laravel Boost development telemetry is outside the application API.',
            ],
            'PUT api/admin/ai-usage-limits' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative setting command needs an explicit actor, safe field list, and outcome event.',
            ],
            'POST api/admin/announcements' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for POST api/admin/announcements is covered by its model lifecycle event without duplicate request logging.',
            ],
            'PATCH api/admin/announcements/{announcement}' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for PATCH api/admin/announcements/{announcement} is covered by its model lifecycle event without duplicate request logging.',
            ],
            'DELETE api/admin/announcements/{announcement}' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for DELETE api/admin/announcements/{announcement} is covered by its model lifecycle event without duplicate request logging.',
            ],
            'PUT api/admin/food-service-settings' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative setting command needs an explicit actor, safe field list, and outcome event.',
            ],
            'POST api/admin/report-branding' => [
                'classification' => 'explicit_event',
                'reason' => 'This report-configuration command needs an explicit safe allow-listed change event; images and arbitrary payloads stay excluded.',
            ],
            'DELETE api/admin/reports/{report}' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/admin/reports/{type}/archive' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/admin/users' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative account command needs an explicit actor, target, and outcome event without credential values.',
            ],
            'PUT api/admin/users/{user}' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative account command needs an explicit actor, target, and outcome event without credential values.',
            ],
            'PATCH api/admin/users/{user}' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative account command needs an explicit actor, target, and outcome event without credential values.',
            ],
            'DELETE api/admin/users/{user}' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative account command needs an explicit actor, target, and outcome event without credential values.',
            ],
            'POST api/admin/users/{user}/reset-password' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative account command needs an explicit actor, target, and outcome event without credential values.',
            ],
            'POST api/auth/forgot-password' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/auth/login' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/auth/logout' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/auth/password' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'PATCH api/auth/profile' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'PATCH api/auth/recovery-email' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/auth/recovery-email/verify' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/auth/reset-password' => [
                'classification' => 'explicit_event',
                'reason' => 'This authentication or account-security command needs an explicit outcome event; credentials, tokens, and codes stay excluded.',
            ],
            'POST api/fss/budgets' => [
                'classification' => 'explicit_event',
                'reason' => 'This budget command needs an explicit business event so adjustment intent and outcome are preserved safely.',
            ],
            'POST api/fss/budgets/adjust' => [
                'classification' => 'explicit_event',
                'reason' => 'This budget command needs an explicit business event so adjustment intent and outcome are preserved safely.',
            ],
            'POST api/fss/diet-list-counts' => [
                'classification' => 'model_event',
                'reason' => 'Diet-list count persistence for POST api/fss/diet-list-counts is covered by its model lifecycle event without duplicate request logging.',
            ],
            'POST api/fss/food-service-recipes' => [
                'classification' => 'model_event',
                'reason' => 'Food-service recipe persistence for POST api/fss/food-service-recipes is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/fss/food-service-recipes/{food_service_recipe}' => [
                'classification' => 'model_event',
                'reason' => 'Food-service recipe persistence for PUT|PATCH api/fss/food-service-recipes/{food_service_recipe} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/food-service-recipes/{food_service_recipe}' => [
                'classification' => 'model_event',
                'reason' => 'Food-service recipe persistence for DELETE api/fss/food-service-recipes/{food_service_recipe} is covered by its model lifecycle event.',
            ],
            'PUT api/fss/food-service-settings' => [
                'classification' => 'explicit_event',
                'reason' => 'This administrative setting command needs an explicit actor, safe field list, and outcome event.',
            ],
            'POST api/fss/fs-items' => [
                'classification' => 'model_event',
                'reason' => 'Food-service catalog persistence for POST api/fss/fs-items is covered by the FsItem model lifecycle event.',
            ],
            'PATCH api/fss/fs-items/{fsItem}' => [
                'classification' => 'model_event',
                'reason' => 'Food-service catalog persistence for PATCH api/fss/fs-items/{fsItem} is covered by the FsItem model lifecycle event.',
            ],
            'DELETE api/fss/fs-items/{fsItem}' => [
                'classification' => 'model_event',
                'reason' => 'Food-service catalog persistence for DELETE api/fss/fs-items/{fsItem} is covered by the FsItem model lifecycle event.',
            ],
            'PATCH api/fss/fs-items/{fsItem}/vendor-lock' => [
                'classification' => 'model_event',
                'reason' => 'Food-service catalog persistence for PATCH api/fss/fs-items/{fsItem}/vendor-lock is covered by the FsItem model lifecycle event.',
            ],
            'POST api/fss/meal-prep-logs/{mealPrepLog}/reverse' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-service lifecycle command needs an explicit domain event describing its business outcome.',
            ],
            'POST api/fss/menu-cycle-templates' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle template persistence for POST api/fss/menu-cycle-templates is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/fss/menu-cycle-templates/{menu_cycle_template}' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle template persistence for PUT|PATCH api/fss/menu-cycle-templates/{menu_cycle_template} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/menu-cycle-templates/{menu_cycle_template}' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle template persistence for DELETE api/fss/menu-cycle-templates/{menu_cycle_template} is covered by its model lifecycle event.',
            ],
            'POST api/fss/menu-cycle-templates/{menu_cycle_template}/instantiate' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle template persistence for POST api/fss/menu-cycle-templates/{menu_cycle_template}/instantiate is covered by its model lifecycle event.',
            ],
            'POST api/fss/menu-cycles' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle persistence for POST api/fss/menu-cycles is covered by its model lifecycle event.',
            ],
            'POST api/fss/menu-cycles/{menuCycle}/complete-day' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-service lifecycle command needs an explicit domain event describing its business outcome.',
            ],
            'PATCH api/fss/menu-cycles/{menuCycle}/served-population' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-service lifecycle command needs an explicit domain event describing its business outcome.',
            ],
            'PUT|PATCH api/fss/menu-cycles/{menu_cycle}' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle persistence for PUT|PATCH api/fss/menu-cycles/{menu_cycle} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/menu-cycles/{menu_cycle}' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle persistence for DELETE api/fss/menu-cycles/{menu_cycle} is covered by its model lifecycle event.',
            ],
            'PATCH api/fss/menu-cycles/{menu_cycle}/activate' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle persistence for PATCH api/fss/menu-cycles/{menu_cycle}/activate is covered by its model lifecycle event.',
            ],
            'POST api/fss/menu-cycles/{menu_cycle}/save-template' => [
                'classification' => 'model_event',
                'reason' => 'Menu-cycle persistence for POST api/fss/menu-cycles/{menu_cycle}/save-template is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/purchase-order-attachments/{attachment}' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'PATCH api/fss/purchase-order-vendor-groups/{vendorGroup}' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'POST api/fss/purchase-order-vendor-groups/{vendorGroup}/attachments' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'PUT|PATCH api/fss/purchase-orders/{purchase_order}' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'DELETE api/fss/purchase-orders/{purchase_order}' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'POST api/fss/purchase-orders/{purchase_order}/attachments' => [
                'classification' => 'explicit_event',
                'reason' => 'This procurement command needs an explicit purchase-order-root event without attachment or receipt contents.',
            ],
            'POST api/fss/report-branding' => [
                'classification' => 'explicit_event',
                'reason' => 'This report-configuration command needs an explicit safe allow-listed change event; images and arbitrary payloads stay excluded.',
            ],
            'PATCH api/fss/report-templates/{reportTemplate}' => [
                'classification' => 'explicit_event',
                'reason' => 'This report-configuration command needs an explicit safe allow-listed change event; images and arbitrary payloads stay excluded.',
            ],
            'POST api/fss/reports' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/fss/reports/generate-all' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'DELETE api/fss/reports/{report}' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/fss/reports/{type}/archive' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'PATCH api/fss/shopping-list-items/{shopping_list_item}' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list item persistence for PATCH api/fss/shopping-list-items/{shopping_list_item} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/shopping-list-items/{shopping_list_item}' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list item persistence for DELETE api/fss/shopping-list-items/{shopping_list_item} is covered by its model lifecycle event.',
            ],
            'POST api/fss/shopping-lists' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list persistence for POST api/fss/shopping-lists is covered by its model lifecycle event.',
            ],
            'POST api/fss/shopping-lists/generate' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list persistence for POST api/fss/shopping-lists/generate is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/fss/shopping-lists/{shopping_list}' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list persistence for PUT|PATCH api/fss/shopping-lists/{shopping_list} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/shopping-lists/{shopping_list}' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list persistence for DELETE api/fss/shopping-lists/{shopping_list} is covered by its model lifecycle event.',
            ],
            'POST api/fss/shopping-lists/{shopping_list}/approve' => [
                'classification' => 'explicit_event',
                'reason' => 'Shopping-list approval needs an explicit approver and outcome event rather than a generic model update.',
            ],
            'POST api/fss/shopping-lists/{shopping_list}/items' => [
                'classification' => 'model_event',
                'reason' => 'Shopping-list persistence for POST api/fss/shopping-lists/{shopping_list}/items is covered by its model lifecycle event.',
            ],
            'POST api/fss/suppliers' => [
                'classification' => 'model_event',
                'reason' => 'Supplier persistence for POST api/fss/suppliers is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/fss/suppliers/{supplier}' => [
                'classification' => 'model_event',
                'reason' => 'Supplier persistence for PUT|PATCH api/fss/suppliers/{supplier} is covered by its model lifecycle event.',
            ],
            'DELETE api/fss/suppliers/{supplier}' => [
                'classification' => 'model_event',
                'reason' => 'Supplier persistence for DELETE api/fss/suppliers/{supplier} is covered by its model lifecycle event.',
            ],
            'PATCH api/notifications/read-all' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Notification read state is routine UI housekeeping.',
            ],
            'PATCH api/notifications/{notification}/read' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Notification read state is routine UI housekeeping.',
            ],
            'POST api/rnd/announcements' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for POST api/rnd/announcements is covered by its model lifecycle event without duplicate request logging.',
            ],
            'PATCH api/rnd/announcements/{announcement}' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for PATCH api/rnd/announcements/{announcement} is covered by its model lifecycle event without duplicate request logging.',
            ],
            'DELETE api/rnd/announcements/{announcement}' => [
                'classification' => 'model_event',
                'reason' => 'Announcement persistence for DELETE api/rnd/announcements/{announcement} is covered by its model lifecycle event without duplicate request logging.',
            ],
            'POST api/rnd/calendar-events' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Calendar reminders are low-risk personal workflow state.',
            ],
            'POST api/rnd/food-items' => [
                'classification' => 'model_event',
                'reason' => 'Food-item persistence for POST api/rnd/food-items is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/rnd/food-items/{food_item}' => [
                'classification' => 'model_event',
                'reason' => 'Food-item persistence for PUT|PATCH api/rnd/food-items/{food_item} is covered by its model lifecycle event.',
            ],
            'DELETE api/rnd/food-items/{food_item}' => [
                'classification' => 'model_event',
                'reason' => 'Food-item persistence for DELETE api/rnd/food-items/{food_item} is covered by its model lifecycle event.',
            ],
            'DELETE api/rnd/meal-plan-templates/{template}' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan template persistence for DELETE api/rnd/meal-plan-templates/{template} is covered by its model lifecycle event.',
            ],
            'DELETE api/rnd/ncp-records/{ncpRecord}' => [
                'classification' => 'model_event',
                'reason' => 'NCP record persistence for DELETE api/rnd/ncp-records/{ncpRecord} is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/assessment' => [
                'classification' => 'model_event',
                'reason' => 'Assessment persistence for POST api/rnd/ncp-records/{ncpRecord}/assessment is covered by its redacted clinical model event.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/assessment' => [
                'classification' => 'model_event',
                'reason' => 'Assessment persistence for PATCH api/rnd/ncp-records/{ncpRecord}/assessment is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/attachments' => [
                'classification' => 'explicit_event',
                'reason' => 'This sensitive attachment command needs an explicit root-context event without paths, file contents, or OCR data.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/diagnoses' => [
                'classification' => 'model_event',
                'reason' => 'Diagnosis persistence for POST api/rnd/ncp-records/{ncpRecord}/diagnoses is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/diagnoses/ai-approve' => [
                'classification' => 'explicit_event',
                'reason' => 'Saving an AI-assisted approval needs an explicit clinical outcome event without prompts or model output.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/diagnoses/ai-suggest' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Unsaved AI suggestions must not persist prompts or outputs.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}' => [
                'classification' => 'model_event',
                'reason' => 'Diagnosis persistence for PATCH api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis} is covered by its redacted clinical model event.',
            ],
            'DELETE api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}' => [
                'classification' => 'model_event',
                'reason' => 'Diagnosis persistence for DELETE api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis} is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/intervention' => [
                'classification' => 'model_event',
                'reason' => 'Intervention persistence for POST api/rnd/ncp-records/{ncpRecord}/intervention is covered by its redacted clinical model event.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/intervention' => [
                'classification' => 'model_event',
                'reason' => 'Intervention persistence for PATCH api/rnd/ncp-records/{ncpRecord}/intervention is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/intervention/autofill' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Unsaved AI output must not persist prompts or outputs.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/intervention/recommend' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Unsaved AI output must not persist prompts or outputs.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/meal-plans' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for POST api/rnd/ncp-records/{ncpRecord}/meal-plans is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/meal-plans/from-template' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for POST api/rnd/ncp-records/{ncpRecord}/meal-plans/from-template is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/meal-plans/generate' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for POST api/rnd/ncp-records/{ncpRecord}/meal-plans/generate is covered by its redacted clinical model event.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for PATCH api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan} is covered by its redacted clinical model event.',
            ],
            'DELETE api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for DELETE api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan} is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-plan child command needs an explicit NCP-root event; clinical values remain excluded.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item}' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-plan child command needs an explicit NCP-root event; clinical values remain excluded.',
            ],
            'DELETE api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item}' => [
                'classification' => 'explicit_event',
                'reason' => 'This meal-plan child command needs an explicit NCP-root event; clinical values remain excluded.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template' => [
                'classification' => 'model_event',
                'reason' => 'Meal-plan persistence for POST api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/monitorings' => [
                'classification' => 'model_event',
                'reason' => 'Monitoring persistence for POST api/rnd/ncp-records/{ncpRecord}/monitorings is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/ncp-records/{ncpRecord}/monitorings/ai-review' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Unsaved AI review output must not persist prompts or outputs.',
            ],
            'PATCH api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}' => [
                'classification' => 'model_event',
                'reason' => 'Monitoring persistence for PATCH api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring} is covered by its redacted clinical model event.',
            ],
            'DELETE api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}' => [
                'classification' => 'model_event',
                'reason' => 'Monitoring persistence for DELETE api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring} is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/patients' => [
                'classification' => 'model_event',
                'reason' => 'Patient persistence for POST api/rnd/patients is covered by its redacted clinical model event.',
            ],
            'PUT|PATCH api/rnd/patients/{patient}' => [
                'classification' => 'model_event',
                'reason' => 'Patient persistence for PUT|PATCH api/rnd/patients/{patient} is covered by its redacted clinical model event.',
            ],
            'DELETE api/rnd/patients/{patient}' => [
                'classification' => 'model_event',
                'reason' => 'Patient persistence for DELETE api/rnd/patients/{patient} is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/patients/{patient}/ncp-records' => [
                'classification' => 'model_event',
                'reason' => 'NCP record persistence for POST api/rnd/patients/{patient}/ncp-records is covered by its redacted clinical model event.',
            ],
            'POST api/rnd/recipes' => [
                'classification' => 'model_event',
                'reason' => 'Recipe persistence for POST api/rnd/recipes is covered by its model lifecycle event.',
            ],
            'PUT|PATCH api/rnd/recipes/{recipe}' => [
                'classification' => 'model_event',
                'reason' => 'Recipe persistence for PUT|PATCH api/rnd/recipes/{recipe} is covered by its model lifecycle event.',
            ],
            'DELETE api/rnd/recipes/{recipe}' => [
                'classification' => 'model_event',
                'reason' => 'Recipe persistence for DELETE api/rnd/recipes/{recipe} is covered by its model lifecycle event.',
            ],
            'POST api/rnd/report-branding' => [
                'classification' => 'explicit_event',
                'reason' => 'This report-configuration command needs an explicit safe allow-listed change event; images and arbitrary payloads stay excluded.',
            ],
            'PATCH api/rnd/report-templates/{reportTemplate}' => [
                'classification' => 'explicit_event',
                'reason' => 'This report-configuration command needs an explicit safe allow-listed change event; images and arbitrary payloads stay excluded.',
            ],
            'POST api/rnd/reports' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/rnd/reports/generate-all' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'DELETE api/rnd/reports/{report}' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'POST api/rnd/reports/{type}/archive' => [
                'classification' => 'explicit_event',
                'reason' => 'This report lifecycle command needs an explicit outcome event without report snapshots, filters, or file contents.',
            ],
            'DELETE api/rnd/screening-documents/{screeningDocument}' => [
                'classification' => 'explicit_event',
                'reason' => 'This sensitive attachment command needs an explicit root-context event without paths, file contents, or OCR data.',
            ],
            'POST api/rnd/usda/import/{fdcId}' => [
                'classification' => 'model_event',
                'reason' => 'USDA import persistence for POST api/rnd/usda/import/{fdcId} is covered by the created catalog model event.',
            ],
            'POST api/sop' => [
                'classification' => 'model_event',
                'reason' => 'SOP version persistence for POST api/sop is covered by its model lifecycle event.',
            ],
            'PUT storage/{path}' => [
                'classification' => 'intentionally_not_audited',
                'reason' => 'Laravel local-storage serving is framework infrastructure, not an application command.',
            ],
        ];

        $expectedUnsafeRoutes = array_keys($coverage);
        sort($expectedUnsafeRoutes);
        $this->assertSame($expectedUnsafeRoutes, $actualUnsafeRoutes, 'Unsafe route inventory changed; classify every added or removed route.');

        collect($coverage)->each(function (array $policy, string $route): void {
            $this->assertContains($policy['classification'], ['explicit_event', 'model_event', 'intentionally_not_audited'], $route);
            $this->assertNotSame('', trim($policy['reason']), $route);
        });
    }
}
