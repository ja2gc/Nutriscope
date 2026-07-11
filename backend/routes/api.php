<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AiUsageLimitController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RecoveryEmailController;
use App\Http\Controllers\FSS\BudgetController;
use App\Http\Controllers\FSS\DashboardController as FssDashboardController;
use App\Http\Controllers\FSS\DietListCountController;
use App\Http\Controllers\FSS\FoodServiceRecipeController;
use App\Http\Controllers\FSS\FoodServiceSettingController;
use App\Http\Controllers\FSS\FsItemController;
use App\Http\Controllers\FSS\InventoryController;
use App\Http\Controllers\FSS\MealPrepLogController;
use App\Http\Controllers\FSS\MenuCycleController;
use App\Http\Controllers\FSS\MenuCycleTemplateController;
use App\Http\Controllers\FSS\PurchaseOrderController;
use App\Http\Controllers\FSS\ShoppingListController;
use App\Http\Controllers\FSS\SupplierController;
use App\Http\Controllers\ReportBrandingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportTemplateController;
use App\Http\Controllers\RND\AiDiagnosisController;
use App\Http\Controllers\RND\AnnouncementController as RndAnnouncementController;
use App\Http\Controllers\RND\AssessmentController;
use App\Http\Controllers\RND\CalendarEventController;
use App\Http\Controllers\RND\DiagnosisController;
use App\Http\Controllers\RND\FoodItemController;
use App\Http\Controllers\RND\InterventionController;
use App\Http\Controllers\RND\MealPlanController;
use App\Http\Controllers\RND\MealPlanItemController;
use App\Http\Controllers\RND\MonitoringController;
use App\Http\Controllers\RND\NcpRecordController;
use App\Http\Controllers\RND\NotificationController;
use App\Http\Controllers\RND\PatientController;
use App\Http\Controllers\RND\RecipeController;
use App\Http\Controllers\RND\ScreeningDocumentController;
use App\Http\Controllers\RND\UsdaController;
use App\Http\Controllers\SopController;
use Illuminate\Support\Facades\Route;

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
    Route::middleware('throttle:reports')->group(function () {
        Route::get('reports/{type}/render', [ReportController::class, 'render'])->where('type', '[a-z_]+');
        Route::post('reports/{type}/archive', [ReportController::class, 'archive'])->where('type', '[a-z_]+');
    });
    Route::get('reports/{report}/download', [ReportController::class, 'download']);
    Route::get('reports/{report}/view', [ReportController::class, 'view']);
    Route::apiResource('reports', ReportController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('report-branding', [ReportBrandingController::class, 'show']);
    Route::post('report-branding', [ReportBrandingController::class, 'update']);
    Route::get('report-templates', [ReportTemplateController::class, 'index']);
    Route::patch('report-templates/{reportTemplate}', [ReportTemplateController::class, 'update']);
};

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::patch('recovery-email', [RecoveryEmailController::class, 'update'])
            ->middleware('throttle:password-reset');
        Route::post('recovery-email/verify', [RecoveryEmailController::class, 'verify'])
            ->middleware('throttle:password-reset');
        Route::post('password', [AuthController::class, 'updatePassword'])->middleware('throttle:password-change');
    });
});

// Shared notification routes — accessible to any authenticated role (RND, FSS, Admin).
// The controller already scopes strictly by Auth::id(), so each user sees only their own rows.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);

    // SOP — all roles read the current procedure + its history; RND/Admin author.
    Route::get('sop', [SopController::class, 'current']);
    Route::get('sop/history', [SopController::class, 'history']);
    Route::middleware('role:RND,Admin')->post('sop', [SopController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'active', 'role:RND'])->prefix('rnd')->group(function () use ($reportRoutes) {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/activity', [ActivityController::class, 'patient']);
    Route::get('patients/{patient}/ncp-records', [PatientController::class, 'ncpRecords']);
    Route::post('patients/{patient}/ncp-records', [PatientController::class, 'startNcpCycle']);
    Route::delete('ncp-records/{ncpRecord}', [NcpRecordController::class, 'destroy']);
    Route::get('announcements', [RndAnnouncementController::class, 'index']);
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('announcements', [RndAnnouncementController::class, 'store']);
        Route::patch('announcements/{announcement}', [RndAnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}', [RndAnnouncementController::class, 'destroy']);
    });

    // Assessment routes
    Route::post('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/assessment', [AssessmentController::class, 'update']);
    // NCP supporting-document attachments (rnd.md §3.1) — plain storage, per-cycle scoped.
    Route::post('ncp-records/{ncpRecord}/attachments', [AssessmentController::class, 'uploadAttachment'])->middleware('throttle:uploads');
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
    Route::middleware('throttle:ai')->group(function () {
        Route::post('ncp-records/{ncpRecord}/diagnoses/ai-suggest', [AiDiagnosisController::class, 'aiSuggest']);
        Route::post('ncp-records/{ncpRecord}/diagnoses/ai-approve', [AiDiagnosisController::class, 'aiApprove']);
    });

    // Intervention routes
    Route::middleware('throttle:compute')->group(function () {
        Route::post('ncp-records/{ncpRecord}/intervention/autofill', [InterventionController::class, 'autofill']);
        Route::post('ncp-records/{ncpRecord}/intervention/recommend', [MealPlanController::class, 'recommend']);
        Route::get('ncp-records/{ncpRecord}/intervention/recommendations', [InterventionController::class, 'recommendations']);
    });
    Route::post('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/intervention', [InterventionController::class, 'update']);

    // Meal Plan routes
    Route::get('ncp-records/{ncpRecord}/meal-plans', [MealPlanController::class, 'index']);
    Route::post('ncp-records/{ncpRecord}/meal-plans', [MealPlanController::class, 'store']);
    Route::get('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
    Route::patch('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'update']);
    Route::delete('ncp-records/{ncpRecord}/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);
    Route::post('ncp-records/{ncpRecord}/meal-plans/generate', [MealPlanController::class, 'generate'])->middleware('throttle:ai');
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
    Route::get('ncp-records/{ncpRecord}/monitoring-plan', [MonitoringController::class, 'plan']);
    Route::get('ncp-records/{ncpRecord}/monitorings', [MonitoringController::class, 'index']);
    // Phase 6 — evaluation summary (free) + optional AI narrative (declared before
    // the {monitoring} routes so the literal segments win the match).
    Route::get('ncp-records/{ncpRecord}/monitorings/summary', [MonitoringController::class, 'summary']);
    Route::post('ncp-records/{ncpRecord}/monitorings/ai-review', [MonitoringController::class, 'aiReview'])->middleware('throttle:ai');
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
    Route::middleware('throttle:usda')->group(function () {
        Route::get('usda/search', [UsdaController::class, 'search']);
        Route::post('usda/import/{fdcId}', [UsdaController::class, 'import']);
        Route::get('usda/preview/{fdcId}', [UsdaController::class, 'preview'])
            ->where('fdcId', '[0-9]+');
    });
});

Route::middleware(['auth:sanctum', 'active', 'role:FSS,RND'])->prefix('fss')->group(function () use ($reportRoutes) {
    // Dashboard
    Route::get('dashboard/summary', [FssDashboardController::class, 'summary']);

    // Inventory routes - backend reference catalog only, no FSS stocking
    Route::get('inventory/rows', [InventoryController::class, 'rows']);
    Route::apiResource('inventory', InventoryController::class)->only(['index', 'show']);

    // Purchase Orders — FSS reads + attachments; writes are RND-only (see below)
    Route::get('purchase-orders/{purchase_order}/activity', [ActivityController::class, 'purchaseOrder']);
    Route::post('purchase-orders/{purchase_order}/attachments', [PurchaseOrderController::class, 'uploadAttachment'])->middleware('throttle:uploads');
    Route::patch('purchase-order-vendor-groups/{vendorGroup}', [PurchaseOrderController::class, 'updateVendorGroup']);
    Route::post('purchase-order-vendor-groups/{vendorGroup}/attachments', [PurchaseOrderController::class, 'uploadVendorGroupAttachment'])->middleware('throttle:uploads');
    Route::delete('purchase-order-attachments/{attachment}', [PurchaseOrderController::class, 'destroyAttachment']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show']);

    // Shopping Lists — FSS reads only; writes are RND-only (see below)
    Route::apiResource('shopping-lists', ShoppingListController::class)->only(['index', 'show']);

    // Per-day served population (backfill from menu cycle) — editable by FSS + RND.
    Route::patch('menu-cycles/{menuCycle}/served-population', [MealPrepLogController::class, 'setServed']);

    // FS Items — catalog list (ready-to-serve picker) + price-trend read for FSS;
    // update is RND-only (see below)
    Route::get('fs-items', [FsItemController::class, 'index']);
    // Reference catalog (ingredients + supplies) used to build recipes & procurement.
    Route::get('fs-items/catalog', [FsItemController::class, 'catalog']);
    Route::get('fs-items/{fsItem}/profile', [FsItemController::class, 'profile']);
    Route::get('fs-items/{fsItem}/price-trend', [FsItemController::class, 'priceTrend']);

    // Menu Cycles — FSS read-only (RND owns writes, see RND-only group below)
    Route::get('menu-cycles/cost-today', [MenuCycleController::class, 'costToday']);
    Route::get('menu-cycles/{menu_cycle}/compute', [MenuCycleController::class, 'compute']);
    Route::apiResource('menu-cycles', MenuCycleController::class)->only(['index', 'show']);

    // Food Service Recipes — FSS read-only (RND owns writes)
    Route::get('food-service-recipes/{foodServiceRecipe}/profile', [FoodServiceRecipeController::class, 'profile']);
    Route::apiResource('food-service-recipes', FoodServiceRecipeController::class)->only(['index', 'show']);

    // Budgets — fiscal year summary + ledger (FSS read-only, RND owns writes)
    Route::get('budgets/summary', [BudgetController::class, 'summary']);
    Route::get('budgets/ledger', [BudgetController::class, 'ledger']);
    Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);

    // Food Service settings — budget per head per day (FSS read, RND writes below)
    Route::get('food-service-settings', [FoodServiceSettingController::class, 'show']);

    // Per-record audit history (Spec 5)
    Route::get('inventory/{inventory}/activity', [ActivityController::class, 'inventory']);

    // Consumption (meal prep / service-day completion)
    Route::get('meal-prep-logs', [MealPrepLogController::class, 'index']);
    Route::post('menu-cycles/{menuCycle}/complete-day', [MealPrepLogController::class, 'complete']);
    Route::post('meal-prep-logs/{mealPrepLog}/reverse', [MealPrepLogController::class, 'reverse']);

    // Diet-list capture (per-staff ward headcount + task marks)
    Route::post('diet-list-counts', [DietListCountController::class, 'store']);
    Route::get('diet-list-counts', [DietListCountController::class, 'index']);

    // ===== RND-only planning/authoring writes (FSS receives 403) =====
    Route::middleware('role:RND')->group(function () {
        // Suppliers — RND-only (FSS has no read scope either per §6)
        Route::apiResource('suppliers', SupplierController::class);

        // Purchase Orders — created only by approving a shopping list (no manual create).
        // RND can still edit/receive/delete the per-vendor orders the approval produced.
        Route::get('purchase-orders/{purchase_order}/ppa', [PurchaseOrderController::class, 'ppa']);
        Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['update', 'destroy']);
        Route::post('shopping-lists/{shopping_list}/approve', [PurchaseOrderController::class, 'approve'])->middleware('throttle:10,1');

        // Shopping Lists — RND authors and generates lists.
        Route::post('shopping-lists/generate', [ShoppingListController::class, 'generate']);
        Route::post('shopping-lists/{shopping_list}/items', [ShoppingListController::class, 'storeItem']);
        Route::patch('shopping-list-items/{shopping_list_item}', [ShoppingListController::class, 'updateItem']);
        Route::delete('shopping-list-items/{shopping_list_item}', [ShoppingListController::class, 'destroyItem']);
        Route::apiResource('shopping-lists', ShoppingListController::class)->only(['store', 'update', 'destroy']);

        // FS Item catalog edits
        Route::post('fs-items', [FsItemController::class, 'store']);
        Route::patch('fs-items/{fsItem}', [FsItemController::class, 'update']);
        Route::patch('fs-items/{fsItem}/vendor-lock', [FsItemController::class, 'toggleDefaultSupplierLock']);
        Route::delete('fs-items/{fsItem}', [FsItemController::class, 'destroy']);

        // Menu Cycles
        Route::patch('menu-cycles/{menu_cycle}/activate', [MenuCycleController::class, 'activate']);
        Route::post('menu-cycles/{menu_cycle}/save-template', [MenuCycleTemplateController::class, 'fromCycle']);
        Route::apiResource('menu-cycles', MenuCycleController::class)->only(['store', 'update', 'destroy']);

        Route::post('menu-cycle-templates/{menu_cycle_template}/instantiate', [MenuCycleTemplateController::class, 'instantiate']);
        Route::apiResource('menu-cycle-templates', MenuCycleTemplateController::class);

        Route::apiResource('food-service-recipes', FoodServiceRecipeController::class)->only(['store', 'update', 'destroy']);

        Route::post('budgets/adjust', [BudgetController::class, 'manualAdjust']);
        Route::apiResource('budgets', BudgetController::class)->only(['store']);

        // Food Service settings — budget per head per day (RND writes)
        Route::put('food-service-settings', [FoodServiceSettingController::class, 'update']);
    });

    // Announcements — FSS reads its feed (visibility FSS|All); RND/Admin own writes
    Route::get('announcements', [RndAnnouncementController::class, 'index']);

    // Reports routes (shared with RND — see $reportRoutes above)
    $reportRoutes();
});

Route::middleware(['auth:sanctum', 'active', 'role:Admin'])->prefix('admin')->group(function () {
    Route::get('announcements', [AdminAnnouncementController::class, 'index']);
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('announcements', [AdminAnnouncementController::class, 'store']);
        Route::patch('announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
    });
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
        ->middleware('throttle:6,1');
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('users', [AdminUserController::class, 'store']);
        Route::put('users/{user}', [AdminUserController::class, 'update']);
        Route::patch('users/{user}', [AdminUserController::class, 'update']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    });
    Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('dashboard', AdminDashboardController::class);
    Route::get('budgets/summary', [BudgetController::class, 'summary']);
    Route::get('budgets/ledger', [BudgetController::class, 'ledger']);
    Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);
    Route::get('report-branding', [ReportBrandingController::class, 'show']);
    Route::post('report-branding', [ReportBrandingController::class, 'update']);
    Route::get('ai-usage-limits', [AiUsageLimitController::class, 'show']);
    Route::put('ai-usage-limits', [AiUsageLimitController::class, 'update']);

    // Food Service settings — budget per head per day (Admin reads + writes)
    Route::get('food-service-settings', [FoodServiceSettingController::class, 'show']);
    Route::put('food-service-settings', [FoodServiceSettingController::class, 'update']);

    // Reports browse: Admin-scoped subset with RND parity except patient-specific reports.
    // ReportController::guardAdmin() enforces the allowlist; 403 for any other type.
    Route::get('reports/{type}/instances', [ReportController::class, 'instances'])->where('type', '[a-z_]+');
    Route::get('reports/{type}/render', [ReportController::class, 'render'])->where('type', '[a-z_]+');
    Route::post('reports/{type}/archive', [ReportController::class, 'archive'])->where('type', '[a-z_]+');
    Route::get('reports/{report}/download', [ReportController::class, 'download']);
    Route::get('reports/{report}/view', [ReportController::class, 'view']);
    Route::apiResource('reports', ReportController::class)->only(['index', 'show', 'destroy']);
});
