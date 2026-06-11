<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
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
use App\Http\Controllers\FSS\BudgetController;
use App\Http\Controllers\FSS\FoodServiceRecipeController;
use App\Http\Controllers\ReportController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:RND', 'audit'])->prefix('rnd')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/ncp-records', [PatientController::class, 'ncpRecords']);
    Route::post('patients/{patient}/ncp-records', [PatientController::class, 'startNcpCycle']);
    Route::delete('ncp-records/{ncpRecord}', [NcpRecordController::class, 'destroy']);
    Route::apiResource('announcements', RndAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);

    // Assessment routes
    Route::post('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'update']);
    Route::post('ncp-records/{ncpRecord}/upload-screening', [AssessmentController::class, 'uploadScreening']);
    Route::post('ncp-records/{ncpRecord}/upload-labs', [AssessmentController::class, 'uploadLabs']);
    Route::get('ncp-records/{ncpRecord}/screening-document', [AssessmentController::class, 'showScreeningDocument']);
    Route::get('ncp-records/{ncpRecord}/ocr-documents', [AssessmentController::class, 'showOcrDocuments']);
    Route::get('screening-documents/{screeningDocument}', [ScreeningDocumentController::class, 'show']);
    Route::patch('screening-documents/{screeningDocument}/approve', [ScreeningDocumentController::class, 'approve']);
    Route::get('screening-documents/{screeningDocument}/file', [ScreeningDocumentController::class, 'file']);
    Route::get('ocr-documents/{ocrDocument}/file', [AssessmentController::class, 'showOcrDocumentFile']);

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

    // Notifications routes
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);

    // Reports routes
    Route::apiResource('reports', ReportController::class)->only(['index', 'store', 'show']);

    // Food Database routes
    Route::apiResource('food-items', FoodItemController::class);
    Route::apiResource('recipes', RecipeController::class);
    Route::get('usda/search', [UsdaController::class, 'search']);
    Route::post('usda/import/{fdcId}', [UsdaController::class, 'import']);
    Route::get('usda/preview/{fdcId}', [UsdaController::class, 'preview'])
        ->where('fdcId', '[0-9]+');
});

Route::middleware(['auth:sanctum', 'role:FSS,RND'])->prefix('fss')->group(function () {
    // Inventory routes
    Route::get('inventory/rows', [InventoryController::class, 'rows']);
    Route::apiResource('inventory', InventoryController::class);
    Route::post('inventory/{inventory}/restock', [InventoryController::class, 'restock']);

    // Suppliers routes
    Route::apiResource('suppliers', SupplierController::class);

    // Purchase Orders routes
    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    // Shopping Lists routes
    Route::post('shopping-lists/generate', [ShoppingListController::class, 'generate']);
    Route::apiResource('shopping-lists', ShoppingListController::class);

    // Menu Cycles routes
    Route::patch('menu-cycles/{menu_cycle}/activate', [MenuCycleController::class, 'activate']);
    Route::apiResource('menu-cycles', MenuCycleController::class);

    // Food Service Recipes routes
    Route::apiResource('food-service-recipes', FoodServiceRecipeController::class);

    // Budgets routes
    Route::post('budgets/{budget}/daily-logs', [BudgetController::class, 'storeDailyLog']);
    Route::apiResource('budgets', BudgetController::class);

    // Reports routes
    Route::apiResource('reports', ReportController::class)->only(['index', 'store', 'show']);
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')->group(function () {
    Route::apiResource('announcements', AdminAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('users', AdminUserController::class);
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
});
