<?php

namespace App\Services\FSS;

use App\Models\MenuCycle;
use App\Models\User;
use App\Services\MenuCycleCostService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MaintainMenuCycles
{
    public static function lockCreationOwner(): ?User
    {
        return User::query()->where('role', 'RND')->where('is_active', true)
            ->orderBy('id')->lockForUpdate()->first();
    }

    public function handle(): ?MenuCycle
    {
        $week = CarbonImmutable::now('Asia/Manila')->startOfWeek(CarbonInterface::MONDAY);

        return DB::transaction(function () use ($week): ?MenuCycle {
            $owner = self::lockCreationOwner();
            if ($owner === null) {
                return null;
            }

            $current = null;
            for ($offset = 0; $offset <= 1; $offset++) {
                $start = $week->addWeeks($offset)->toDateString();
                $cycle = MenuCycle::query()->whereDate('week_start_date', $start)
                    ->orderByDesc('id')->first();
                if ($cycle === null) {
                    $cycle = MenuCycle::create([
                        'rnd_user_id' => $owner->id,
                        'name' => MenuCycle::defaultName($start),
                        'cycle_days' => 7,
                        'week_start_date' => $start,
                        'is_active' => false,
                        'status' => 'upcoming',
                    ]);
                }
                if ($offset === 0) {
                    $current = $cycle;
                }
            }

            MenuCycle::query()->where('is_active', true)->where('id', '!=', $current->id)
                ->get()->each(function (MenuCycle $cycle) use ($week): void {
                    if ($cycle->week_start_date->lt($week) && $cycle->cost_snapshot === null
                        && $cycle->days()->exists()) {
                        $cycle->cost_snapshot = MenuCycleCostService::forCycle($cycle);
                        $cycle->cost_snapshot_at = now();
                    }
                    $cycle->is_active = false;
                    $cycle->status = $cycle->week_start_date->lt($week) ? 'completed' : 'upcoming';
                    $cycle->save();
                });

            if (! $current->is_active || $current->status !== 'active') {
                $current->is_active = true;
                $current->status = 'active';
                $current->activation_date ??= $week->toDateString();
                $current->save();
            }

            return $current;
        });
    }
}
