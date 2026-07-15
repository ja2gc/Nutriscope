<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\Budget;
use App\Models\FoodItem;
use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class AuditEntityPresenter
{
    private const ENTITY_LABELS = [
        'AiUsageLimit' => ['ai_usage_limit', 'AI usage limit'],
        'Announcement' => ['announcement', 'Announcement'],
        'Assessment' => ['assessment', 'Assessment'],
        'AuditSetting' => ['audit_setting', 'Audit setting'],
        'Budget' => ['budget', 'Budget'],
        'BudgetLedger' => ['budget_ledger', 'Budget ledger'],
        'Diagnosis' => ['diagnosis', 'Diagnosis'],
        'DietListCount' => ['diet_list_count', 'Diet list count'],
        'FoodItem' => ['food_item', 'Food item'],
        'FoodServiceRecipe' => ['food_service_recipe', 'Food service recipe'],
        'FoodServiceSetting' => ['food_service_setting', 'Food service setting'],
        'FsItem' => ['fs_item', 'Food service item'],
        'Intervention' => ['intervention', 'Intervention'],
        'Inventory' => ['inventory', 'Inventory record'],
        'MealPlan' => ['meal_plan', 'Meal plan'],
        'MealPlanDay' => ['meal_plan_day', 'Meal plan day'],
        'MealPlanItem' => ['meal_plan_item', 'Meal plan item'],
        'MealPlanTemplate' => ['meal_plan_template', 'Meal plan template'],
        'MealPrepLog' => ['meal_prep_log', 'Meal preparation log'],
        'MenuCycle' => ['menu_cycle', 'Menu cycle'],
        'MenuCycleDay' => ['menu_cycle_day', 'Menu cycle day'],
        'MenuCycleTemplate' => ['menu_cycle_template', 'Menu cycle template'],
        'Monitoring' => ['monitoring', 'Monitoring record'],
        'NcpRecord' => ['ncp_record', 'NCP record'],
        'Notification' => ['notification', 'Notification'],
        'Patient' => ['patient', 'Patient'],
        'ProgramProjectActivity' => ['program_project_activity', 'Program/project activity'],
        'PurchaseOrder' => ['purchase_order', 'Purchase order'],
        'PurchaseOrderAttachment' => ['purchase_order_attachment', 'Purchase order attachment'],
        'PurchaseOrderItem' => ['purchase_order_item', 'Purchase order item'],
        'PurchaseOrderItemCorrection' => ['purchase_order_item_correction', 'Purchase order item correction'],
        'PurchaseOrderVendorGroup' => ['purchase_order_vendor_group', 'Purchase order vendor group'],
        'Recipe' => ['recipe', 'Recipe'],
        'Report' => ['report', 'Report'],
        'ReportBranding' => ['report_branding', 'Report branding'],
        'ReportTemplate' => ['report_template', 'Report template'],
        'ScreeningDocument' => ['screening_document', 'Screening document'],
        'ShoppingList' => ['shopping_list', 'Shopping list'],
        'ShoppingListItem' => ['shopping_list_item', 'Shopping list item'],
        'Sop' => ['sop', 'Standard operating procedure'],
        'Supplier' => ['supplier', 'Supplier'],
        'User' => ['user', 'User account'],
    ];

    /** @return array{type: string, id: ?string, label: string} */
    public function present(
        ?string $class,
        ?string $publicId,
        bool $clinical,
        string $action,
    ): array {
        if ($class === null || $class === '') {
            return $this->semantic($action);
        }

        $entity = str_starts_with($class, 'App\\Models\\')
            ? (self::ENTITY_LABELS[class_basename($class)] ?? ['record', 'Record'])
            : ['record', 'Record'];

        return [
            'type' => $entity[0],
            'id' => $clinical ? null : $this->uuid($publicId),
            'label' => $entity[1],
        ];
    }

    /** @return array{type: string, id: null, label: string} */
    private function semantic(string $action): array
    {
        [$type, $label] = match ($action) {
            AuditAction::LoginSucceeded->value,
            AuditAction::LoginFailed->value,
            AuditAction::AuthenticationFailed->value,
            AuditAction::Logout->value,
            AuditAction::PasswordChanged->value,
            AuditAction::PasswordReset->value,
            AuditAction::RecoveryEmailChanged->value,
            AuditAction::RecoveryEmailVerified->value => ['admin_web_login', 'Admin web login'],
            AuditAction::AuthorizationDenied->value,
            AuditAction::RateLimitExceeded->value => ['protected_route', 'Protected route'],
            AuditAction::AuditLogViewed->value,
            AuditAction::Exported->value => ['audit_oversight', 'Audit oversight'],
            AuditAction::SettingsChanged->value => ['retention_setting', 'Retention setting'],
            default => ['system_operation', 'System operation'],
        };

        return ['type' => $type, 'id' => null, 'label' => $label];
    }

    public function currentRecordUrl(?Model $record, ?User $viewer): ?string
    {
        if ($record === null || ! $record->exists || $viewer === null || ! $viewer->is_active) {
            return null;
        }

        $uuid = $this->uuid($record->getAttribute('uuid'));
        if ($uuid === null) {
            return null;
        }

        return match (true) {
            $record instanceof Patient && $viewer->role === 'RND' => "/ncp/patients/{$uuid}",
            $record instanceof FoodItem && $viewer->role === 'RND' => "/food-library/foods/{$uuid}",
            $record instanceof Recipe && $viewer->role === 'RND' => "/food-library/recipes/{$uuid}",
            $record instanceof FsItem && in_array($viewer->role, ['RND', 'FSS'], true) => "/food-service/foods/{$uuid}",
            $record instanceof FoodServiceRecipe && in_array($viewer->role, ['RND', 'FSS'], true) => "/food-service/recipes/{$uuid}",
            $record instanceof Budget && $viewer->role === 'Admin' => '/admin/budget',
            $record instanceof Budget && in_array($viewer->role, ['RND', 'FSS'], true) => '/food-service/budget',
            $record instanceof Report && $viewer->role === 'Admin' => '/admin/reports',
            $record instanceof Report && in_array($viewer->role, ['RND', 'FSS'], true) => '/reports',
            $record instanceof User && $viewer->role === 'Admin' => '/admin/users',
            default => null,
        };
    }

    private function uuid(mixed $value): ?string
    {
        return is_string($value) && Uuid::isValid($value) ? strtolower($value) : null;
    }
}
