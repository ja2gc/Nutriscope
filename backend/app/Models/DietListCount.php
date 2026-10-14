<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietListCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_date',
        'menu_cycle_id',
        'ward',
        'fss_user_id',
        'population',
        'collected_ward_diet_lists',
        'apportioned_distributed_meals',
        'helped_food_prep',
        'stored_supplies',
        'collected_diet_list',
        'apportioned_food',
        'cleaned_utensils',
        'assistant_cook',
        'maintained_cleanliness',
        'off_duty',
    ];

    protected $casts = [
        'service_date' => 'date',
        'population' => 'integer',
        'collected_ward_diet_lists' => 'integer',
        'apportioned_distributed_meals' => 'integer',
        'helped_food_prep' => 'bool',
        'stored_supplies' => 'bool',
        'collected_diet_list' => 'bool',
        'apportioned_food' => 'bool',
        'cleaned_utensils' => 'bool',
        'assistant_cook' => 'bool',
        'maintained_cleanliness' => 'bool',
        'off_duty' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    public function scopeVisibleToReportReviewers(Builder $query, bool $includePending = false): Builder
    {
        $states = $includePending ? ['pending', 'approved'] : ['approved'];

        return $query->whereExists(fn ($report) => $report->selectRaw('1')->from('reports')
            ->whereColumn('reports.user_id', 'diet_list_counts.fss_user_id')
            ->where('reports.type', 'accomplishment_report')
            ->whereColumn('reports.report_covered_from', '<=', 'diet_list_counts.service_date')
            ->whereColumn('reports.report_covered_until', '>=', 'diet_list_counts.service_date')
            ->where(fn ($visible) => $visible->whereIn('reports.review_state', $states)
                ->orWhere(fn ($legacy) => $legacy->whereNull('reports.review_state')
                    ->whereIn('reports.status', ['completed', 'archived'])
                    ->whereRaw('diet_list_counts.created_at <= COALESCE(reports.generated_at, reports.created_at)'))));
    }
}
