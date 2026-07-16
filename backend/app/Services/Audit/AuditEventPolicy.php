<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditModule;
use App\Models\AiUsageLimit;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AuditSetting;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Diagnosis;
use App\Models\DietListCount;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceSetting;
use App\Models\FsItem;
use App\Models\Intervention;
use App\Models\Inventory;
use App\Models\MealPlan;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleTemplate;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderItemCorrection;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\ScreeningDocument;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Sop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditEventPolicy
{
    /**
     * @return array{
     *   module: AuditModule,
     *   category: AuditCategory,
     *   domain: AuditDomain,
     *   privacy: 'clinical'|'safe_operational',
     *   canonical_writer: 'automatic'|'explicit',
     *   detail_mode: 'field_names'|'changes'|'history',
     *   reason_rule: 'none'|'destructive'|'corrective'|'reversal',
     *   revision_serializer: 'budget'|'food_service_recipe'|'menu_cycle'|'menu_cycle_template'|'purchase_order'|'rnd_recipe'|'shopping_list'|null
     * }
     */
    public function forEvent(
        AuditAction $action,
        ?Model $subject,
        AuditCategory $fallbackCategory,
        AuditDomain $fallbackDomain,
    ): array {
        $securityAction = $this->isSecurityAction($action);
        $category = $securityAction
            ? AuditCategory::Security
            : $this->category($subject, $fallbackCategory);
        $domain = $securityAction
            ? $fallbackDomain
            : $this->domain($subject, $fallbackDomain);
        $module = $securityAction
            ? AuditModule::SecurityAdministration
            : $this->module($subject, $domain);
        $revisionSerializer = $category === AuditCategory::Clinical
            ? null
            : $this->revisionSerializer($subject);

        return [
            'module' => $module,
            'category' => $category,
            'domain' => $domain,
            'privacy' => $category === AuditCategory::Clinical ? 'clinical' : 'safe_operational',
            'canonical_writer' => $this->canonicalWriter($action, $subject),
            'detail_mode' => $category === AuditCategory::Clinical
                ? 'field_names'
                : ($revisionSerializer === null ? 'changes' : 'history'),
            'reason_rule' => match ($action) {
                AuditAction::Deleted => 'destructive',
                AuditAction::PriceCorrected => 'corrective',
                AuditAction::Reversed => 'reversal',
                default => 'none',
            },
            'revision_serializer' => $revisionSerializer,
        ];
    }

    public function domain(?Model $subject, AuditDomain $fallback): AuditDomain
    {
        return match (true) {
            $subject instanceof User => AuditDomain::Accounts,
            $subject instanceof Patient => AuditDomain::Patients,
            $subject instanceof NcpRecord,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring,
            $subject instanceof ScreeningDocument,
            $subject instanceof MealPlan => AuditDomain::Ncp,
            $subject instanceof FoodItem,
            $subject instanceof Recipe => AuditDomain::NutritionLibrary,
            $subject instanceof Report,
            $subject instanceof ReportBranding,
            $subject instanceof ReportTemplate => AuditDomain::Reports,
            $subject instanceof Budget,
            $subject instanceof BudgetLedger => AuditDomain::Budget,
            $subject instanceof PurchaseOrder,
            $subject instanceof PurchaseOrderItem,
            $subject instanceof PurchaseOrderAttachment,
            $subject instanceof PurchaseOrderVendorGroup,
            $subject instanceof PurchaseOrderItemCorrection,
            $subject instanceof ProgramProjectActivity,
            $subject instanceof ShoppingList,
            $subject instanceof ShoppingListItem,
            $subject instanceof Supplier => AuditDomain::Procurement,
            $subject instanceof FoodServiceRecipe,
            $subject instanceof FoodServiceSetting,
            $subject instanceof FsItem,
            $subject instanceof Inventory,
            $subject instanceof MealPrepLog,
            $subject instanceof MenuCycle,
            $subject instanceof MenuCycleTemplate,
            $subject instanceof DietListCount => AuditDomain::FoodService,
            $subject instanceof AiUsageLimit,
            $subject instanceof Announcement,
            $subject instanceof AuditSetting,
            $subject instanceof Sop => AuditDomain::System,
            default => $fallback,
        };
    }

    private function category(?Model $subject, AuditCategory $fallback): AuditCategory
    {
        return match (true) {
            $subject instanceof User => AuditCategory::Security,
            $subject instanceof Patient,
            $subject instanceof NcpRecord,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring,
            $subject instanceof ScreeningDocument,
            $subject instanceof MealPlan => AuditCategory::Clinical,
            $subject instanceof Report
                && ($subject->getAttribute('audit_patient_id') !== null
                    || $subject->getAttribute('audit_ncp_record_id') !== null) => AuditCategory::Clinical,
            default => $fallback,
        };
    }

    private function module(?Model $subject, AuditDomain $domain): AuditModule
    {
        return match (true) {
            $subject instanceof Report,
            $subject instanceof ReportBranding,
            $subject instanceof ReportTemplate,
            $domain === AuditDomain::Reports => AuditModule::Reports,
            $subject instanceof Patient,
            $subject instanceof NcpRecord,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring,
            $subject instanceof ScreeningDocument,
            $subject instanceof MealPlan,
            $subject instanceof FoodItem,
            $subject instanceof Recipe,
            in_array($domain, [AuditDomain::Patients, AuditDomain::Ncp, AuditDomain::NutritionLibrary], true) => AuditModule::NutritionCare,
            in_array($domain, [AuditDomain::Budget, AuditDomain::Procurement, AuditDomain::FoodService], true) => AuditModule::FoodServiceOperations,
            default => AuditModule::SecurityAdministration,
        };
    }

    private function canonicalWriter(AuditAction $action, ?Model $subject): string
    {
        if (! in_array($action, [AuditAction::Created, AuditAction::Updated, AuditAction::Deleted], true)) {
            return 'explicit';
        }

        return match (true) {
            $subject instanceof Patient,
            $subject instanceof NcpRecord,
            $subject instanceof Assessment,
            $subject instanceof Diagnosis,
            $subject instanceof Intervention,
            $subject instanceof Monitoring,
            $subject instanceof MealPlan,
            $subject instanceof ScreeningDocument => 'automatic',
            default => 'explicit',
        };
    }

    private function revisionSerializer(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Budget => 'budget',
            $subject instanceof FoodServiceRecipe => 'food_service_recipe',
            $subject instanceof MenuCycle => 'menu_cycle',
            $subject instanceof MenuCycleTemplate => 'menu_cycle_template',
            $subject instanceof PurchaseOrder => 'purchase_order',
            $subject instanceof Recipe => 'rnd_recipe',
            $subject instanceof ShoppingList => 'shopping_list',
            default => null,
        };
    }

    private function isSecurityAction(AuditAction $action): bool
    {
        return in_array($action, [
            AuditAction::ProfileChanged,
            AuditAction::LoginSucceeded,
            AuditAction::LoginFailed,
            AuditAction::AuthenticationFailed,
            AuditAction::Logout,
            AuditAction::PasswordChanged,
            AuditAction::PasswordReset,
            AuditAction::RecoveryEmailChanged,
            AuditAction::RecoveryEmailVerified,
            AuditAction::RateLimitExceeded,
            AuditAction::AuthorizationDenied,
            AuditAction::AccountBlocked,
            AuditAction::AccountUnblocked,
        ], true);
    }
}
