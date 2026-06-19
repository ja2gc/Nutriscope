<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\RND\AnnouncementController as RndAnnouncementController;
use App\Http\Controllers\RND\AssessmentController;
use App\Http\Controllers\RND\DiagnosisController;
use App\Http\Controllers\RND\InterventionController;
use App\Http\Controllers\RND\ScreeningDocumentController;
use App\Http\Controllers\RND\MonitoringController;
use App\Http\Controllers\RND\PatientController;
use App\Http\Controllers\RND\AiDiagnosisController;
use App\Http\Controllers\RND\MealPlanController;
use App\Http\Controllers\RND\CalendarEventController;
use App\Http\Controllers\RND\NotificationController;
use App\Http\Controllers\RND\FoodItemController;
use App\Http\Controllers\RND\RecipeController;
use App\Http\Controllers\RND\UsdaController;
use App\Http\Controllers\RND\MealPlanItemController;
use App\Http\Controllers\RND\NcpRecordController;
use App\Http\Controllers\FSS\InventoryController;
use App\Http\Controllers\FSS\SupplierController;
use App\Http\Controllers\FSS\PurchaseOrderController;
use App\Http\Controllers\FSS\ShoppingListController;
use App\Http\Controllers\FSS\MenuCycleController;
use App\Http\Controllers\FSS\MenuCycleTemplateController;
use App\Http\Controllers\FSS\BudgetController;
use App\Http\Controllers\FSS\FoodServiceRecipeController;
use App\Http\Controllers\FSS\FsItemController;
use App\Http\Controllers\FSS\InsightsController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\FSS\MealPrepLogController;
use App\Http\Controllers\FSS\CleaningLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportBrandingController;
use App\Http\Controllers\ReportTemplateController;

/**
 * Reports routes — identical for the RND and FSS role groups, so defined once here
 * and invoked in both. Spec 4 adds browse ({type}/instances), on-demand render
 * ({type}/render), and archive ({type}/archive) alongside the deprecated generate
 * endpoints. Literal/typed segments are declared before the {report} apiResource so
 * they win the match; {type} is constrained to lowercase so it can't shadow numeric ids.
 */
$reportRoutes = function () {
    Route::post('reports/generate-all', [ReportController::class, 'generateAll']); // deprecated (Spec 4)
    Route::get('reports/{type}/instances', [ReportController::class, 'instances'])->where('type', '[a-z_]+');
    Route::get('reports/{type}/render', [ReportController::class, 'render'])->where('type', '[a-z_]+');
    Route::post('reports/{type}/archive', [ReportController::class, 'archive'])->where('type', '[a-z_]+');
    Route::get('reports/{report}/download', [ReportController::class, 'download']);
    Route::get('reports/{report}/view', [ReportController::class, 'view']);
    Route::apiResource('reports', ReportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('report-branding', [ReportBrandingController::class, 'show']);
    Route::post('report-branding', [ReportBrandingController::class, 'update']);
    Route::get('report-templates', [ReportTemplateController::class, 'index']);
    Route::patch('report-templates/{reportTemplate}', [ReportTemplateController::class, 'update']);
};

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('password', [AuthController::class, 'updatePassword']);
    });
});

// Shared notification routes — accessible to any authenticated role (RND, FSS, Admin).
// The controller already scopes strictly by Auth::id(), so each user sees only their own rows.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
});

Route::middleware(['auth:sanctum', 'role:RND', 'audit'])->prefix('rnd')->group(function () use ($reportRoutes) {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/activity', [ActivityController::class, 'patient']);
    Route::get('patients/{patient}/ncp-records', [PatientController::class, 'ncpRecords']);
    Route::post('patients/{patient}/ncp-records', [PatientController::class, 'startNcpCycle']);
    Route::delete('ncp-records/{ncpRecord}', [NcpRecordController::class, 'destroy']);
    Route::apiResource('announcements', RndAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);

    // Assessment routes
    Route::post('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'update']);
    // NCP supporting-document attachments (rnd.md §3.1) — plain storage, per-cycle scoped.
    Route::post('ncp-records/{ncpRecord}/attachments', [AssessmentController::class, 'uploadAttachment']);
    Route::get('ncp-records/{ncpRecord}/attachments', [AssessmentController::class, 'listAttachments']);
    Route::get('screening-documents/{screeningDocument}', [ScreeningDocumentController::class, 'show']);
    Route::get('screening-documents/{screeningDocument}/file', [ScreeningDocumentController::class, 'file']);
    Route::delete('screening-documents/{screeningDocument}', [ScreeningDocumentController::class, 'destroy']);

    // Diagnoses routes
    Route::get('ncp-records/{ncpRecord}/diagnoses', [DiagnosisController::class, 'index']);
    Route::post('ncp-records/{ncpRecord}/diagnoses', [DiagnosisController::class, 'store']);
    Route::patch('ncp-records/{ncpRecord}/diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);
    Route::delete('ncp-records/{ncpRecord}/diagnoses/{diagnosis}', [DiagnosisController::class, 'destroy']);

    // AI Diagnoses routes
    Route::post('ncp-records/{ncpRecord}/diagnoses/ai-suggest', [AiDiagnosisController::class, 'aiSuggest']);
    Route::post('ncp-records/{ncpRecord}/diagnoses/ai-approve', [AiDiagnosisController::class, 'aiApprove']);

    // Intervention routes
    Route::post('ncp-records/{ncpRecord}/intervention/autofill', [InterventionController::class, 'autofill']);
    Route::post('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'update']);
    Route::post('ncp-records/{ncpRecord}/intervention/recommend', [MealPlanController::class, 'recommend']);
    Route::get('ncp-records/{ncpRecord}/intervention/recommendations', [InterventionController::class, 'recommendations']);

    // Meal Plan routes
    Route::get('ncp-records/{ncpRecord}/meal-plans', [MealPlanController::class, 'index']);
    Route::post('ncp-records/{ncpRecord}/meal-plans', [MealPlanController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'update']);
    Route::delete('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);
    Route::post('ncp-records/{ncpRecord}/meal-plans/generate', [MealPlanController::class, 'generate']);
    Route::post('ncp-records/{ncpRecord}/meal-plans/from-template', [MealPlanController::class, 'fromTemplate']);
    Route::post('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template', [MealPlanController::class, 'saveTemplate']);
    Route::get('meal-plan-templates', [MealPlanController::class, 'templates']);
    Route::get('meal-plan-templates/{template}', [MealPlanController::class, 'showTemplate']);
    Route::delete('meal-plan-templates/{template}', [MealPlanController::class, 'destroyTemplate']);

    // Meal Plan Item routes
    Route::get('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/items', [MealPlanItemController::class, 'allItems']);
    Route::get('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items', [MealPlanItemController::class, 'index']);
    Route::post('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items', [MealPlanItemController::class, 'store']);
    Route::patch('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item}', [MealPlanItemController::class, 'update']);
    Route::delete('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item}', [MealPlanItemController::class, 'destroy']);

    // Monitoring routes
    Route::get('ncp-records/{ncpRecord}/monitorings', [MonitoringController::class, 'index']);
    // Phase 6 — evaluation summary (free) + optional AI narrative (declared before
    // the {monitoring} routes so the literal segments win the match).
    Route::get('ncp-records/{ncpRecord}/monitorings/summary', [MonitoringController::class, 'summary']);
    Route::post('ncp-records/{ncpRecord}/monitorings/ai-review', [MonitoringController::class, 'aiReview']);
    Route::post('ncp-records/{ncpRecord}/monitorings', [MonitoringController::class, 'store']);
    Route::patch('ncp-records/{ncpRecord}/monitorings/{monitoring}', [MonitoringController::class, 'update']);
    Route::delete('ncp-records/{ncpRecord}/monitorings/{monitoring}', [MonitoringController::class, 'destroy']);

    // Calendar Events routes
    Route::post('calendar-events', [CalendarEventController::class, 'store']);
    Route::get('calendar-events', [CalendarEventController::class, 'index']);

    // Reports routes (shared with FSS — see $reportRoutes above)
    $reportRoutes();

    // Food Database routes
    Route::apiResource('food-items', FoodItemController::class);
    Route::apiResource('recipes', RecipeController::class);
    Route::get('usda/search', [UsdaController::class, 'search']);
    Route::post('usda/import/{fdcId}', [UsdaController::class, 'import']);
    Route::get('usda/preview/{fdcId}', [UsdaController::class, 'preview'])
        ->where('fdcId', '[0-9]+');
});

Route::middleware(['auth:sanctum', 'role:FSS,RND'])->prefix('fss')->group(function () use ($reportRoutes) {
    // Inventory routes
    Route::get('inventory/rows', [InventoryController::class, 'rows']);
    Route::apiResource('inventory', InventoryController::class);
    Route::post('inventory/{inventory}/restock', [InventoryController::class, 'restock']);

    // Suppliers routes
    Route::apiResource('suppliers', SupplierController::class);

    // Purchase Orders routes
    Route::post('purchase-orders/{purchase_order}/attachments', [PurchaseOrderController::class, 'uploadAttachment']);
    Route::delete('purchase-order-attachments/{attachment}', [PurchaseOrderController::class, 'destroyAttachment']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    // Shopping Lists routes
    Route::post('shopping-lists/generate', [ShoppingListController::class, 'generate']);
    Route::post('shopping-lists/{shopping_list}/generate-pos', [PurchaseOrderController::class, 'generatePos']);
    Route::post('shopping-lists/{shopping_list}/items', [ShoppingListController::class, 'storeItem']);
    Route::patch('shopping-list-items/{shopping_list_item}', [ShoppingListController::class, 'updateItem']);
    Route::delete('shopping-list-items/{shopping_list_item}', [ShoppingListController::class, 'destroyItem']);
    Route::apiResource('shopping-lists', ShoppingListController::class);

    // Menu Cycles — FSS read-only (RND owns writes, see RND-only group below)
    Route::get('menu-cycles/cost-today', [MenuCycleController::class, 'costToday']);
    Route::get('menu-cycles/{menu_cycle}/compute', [MenuCycleController::class, 'compute']);
    Route::apiResource('menu-cycles', MenuCycleController::class)->only(['index', 'show']);

    // Food Service Recipes — FSS read-only (RND owns writes)
    Route::get('food-service-recipes/{foodServiceRecipe}/profile', [FoodServiceRecipeController::class, 'profile']);
    Route::apiResource('food-service-recipes', FoodServiceRecipeController::class)->only(['index', 'show']);

    // FS Items (catalog) routes
    Route::get('fs-items/{fsItem}/price-trend', [FsItemController::class, 'priceTrend']);
    Route::patch('fs-items/{fsItem}', [FsItemController::class, 'update']);

    // Budgets — FSS read-only (RND owns writes)
    Route::get('budgets/{budget}/summary', [BudgetController::class, 'summary']);
    Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);

    // ===== RND-only planning writes (FSS receives 403) =====
    Route::middleware('role:RND')->group(function () {
        Route::patch('menu-cycles/{menu_cycle}/activate', [MenuCycleController::class, 'activate']);
        Route::post('menu-cycles/{menu_cycle}/save-template', [MenuCycleTemplateController::class, 'fromCycle']);
        Route::apiResource('menu-cycles', MenuCycleController::class)->only(['store', 'update', 'destroy']);

        Route::post('menu-cycle-templates/{menu_cycle_template}/instantiate', [MenuCycleTemplateController::class, 'instantiate']);
        Route::apiResource('menu-cycle-templates', MenuCycleTemplateController::class);

        Route::apiResource('food-service-recipes', FoodServiceRecipeController::class)->only(['store', 'update', 'destroy']);

        Route::post('budgets/{budget}/daily-logs', [BudgetController::class, 'storeDailyLog']);
        Route::apiResource('budgets', BudgetController::class)->only(['store', 'update', 'destroy']);
    });

    // Insights / analytics (read-only, graphs — never in compliance PDFs)
    Route::get('insights/spend-by-supplier', [InsightsController::class, 'spendBySupplier']);
    Route::get('insights/cost-per-head', [InsightsController::class, 'costPerHead']);
    Route::get('insights/consumption', [InsightsController::class, 'consumption']);

    // Per-record audit history (Spec 5)
    Route::get('inventory/{inventory}/activity', [ActivityController::class, 'inventory']);
    Route::get('purchase-orders/{purchase_order}/activity', [ActivityController::class, 'purchaseOrder']);

    // Consumption (meal prep / service-day completion)
    Route::get('meal-prep-logs', [MealPrepLogController::class, 'index']);
    Route::post('menu-cycles/{menuCycle}/complete-day', [MealPrepLogController::class, 'complete']);
    Route::post('meal-prep-logs/{mealPrepLog}/reverse', [MealPrepLogController::class, 'reverse']);

    // Cleaning log (FSS daily sanitation checklist)
    Route::apiResource('cleaning-logs', CleaningLogController::class);

    // Announcements — FSS reads its feed (visibility FSS|All); RND/Admin own writes
    Route::get('announcements', [RndAnnouncementController::class, 'index']);

    // Reports routes (shared with RND — see $reportRoutes above)
    $reportRoutes();
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')->group(function () {
    Route::apiResource('announcements', AdminAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
        ->middleware('throttle:6,1');
    Route::apiResource('users', AdminUserController::class);
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('dashboard', AdminDashboardController::class);
    Route::get('report-branding', [ReportBrandingController::class, 'show']);
    Route::post('report-branding', [ReportBrandingController::class, 'update']);
});
