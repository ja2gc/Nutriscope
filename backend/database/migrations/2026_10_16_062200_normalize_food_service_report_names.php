<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $lists = DB::table('shopping_lists')->where('procurement_track', 'food')
                ->where('list_type', 'suggested')->where('name', 'like', 'Demo Marketing — %')
                ->get(['id', 'name', 'period_start', 'period_end']);
            foreach ($lists as $list) {
                if (! $list->period_start || ! $list->period_end) {
                    continue;
                }
                $expected = 'Demo Marketing — '.CarbonImmutable::parse($list->period_start)->format('M j')
                    .'–'.CarbonImmutable::parse($list->period_end)->format('M j, Y');
                if ($list->name === $expected) {
                    DB::table('shopping_lists')->where('id', $list->id)->where('name', $list->name)
                        ->where('period_start', $list->period_start)->where('period_end', $list->period_end)
                        ->update(['name' => substr($list->name, 5)]);
                }
            }

            $defaults = ['procurement_pack' => 'Procurement Pack', 'program_project_activity' => 'Program Project Activity (PPA)'];
            $reports = DB::table('reports')->whereIn('type', array_keys($defaults))->get(['id', 'type', 'title', 'parameters']);
            foreach ($reports as $report) {
                $prefix = $report->type === 'procurement_pack' ? 'PP' : 'PPA';
                if (str_starts_with($report->title, 'PO — ')) {
                    $title = $prefix.' — '.substr($report->title, strlen('PO — '));
                } elseif ($report->title === $defaults[$report->type]) {
                    $parameters = json_decode($report->parameters ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                    $sourceId = is_array($parameters) ? ($parameters['purchase_order_id'] ?? null) : null;
                    $order = is_scalar($sourceId) ? DB::table('purchase_orders as po')
                        ->leftJoin('shopping_lists as sl', 'sl.id', '=', 'po.shopping_list_id')
                        ->where(ctype_digit((string) $sourceId) ? 'po.id' : 'po.uuid', $sourceId)
                        ->first(['sl.name', 'po.order_date', 'po.completed_at']) : null;
                    $date = $order?->order_date ?? $order?->completed_at;
                    $suffix = $order?->name ?: ($date ? CarbonImmutable::parse($date)->format('M j, Y') : null);
                    $title = $prefix.($suffix ? ' — '.$suffix : '');
                } elseif (str_starts_with($report->title, $prefix.' — ')) {
                    $title = $report->title;
                } else {
                    continue;
                }
                $title = preg_replace('/^'.preg_quote($prefix, '/').' — Demo Marketing — /u', $prefix.' — Marketing — ', $title);
                if ($title !== $report->title) {
                    DB::table('reports')->where('id', $report->id)->where('title', $report->title)->update(['title' => $title]);
                }
            }
        });
    }

    /**
     * Metadata corrections are forward-only; do not restore ambiguous names.
     */
    public function down(): void {}
};
