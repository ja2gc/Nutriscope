<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'RND' => [
                ['Pending PO needs review', 'A food procurement order is waiting for final served population and receipts.', 'procurement', 'purchase_orders', false],
                ['Assessment follow-up due', 'Complete required calculation fields before intervention planning.', 'clinical', 'ncp_records', false],
            ],
            'FSS' => [
                ['Open PO execution', 'Upload vendor receipts/proof and OR number for the active procurement pack.', 'procurement', 'purchase_orders', false],
                ['Served population reminder', 'Backfill served headcount for menu-cycle days before PO completion.', 'food_service', 'menu_cycles', true],
            ],
            'Admin' => [
                ['System report templates ready', 'Food-service report templates are available for review.', 'report', 'reports', true],
                ['User access active', 'Demo Admin, RND, and FSS accounts are seeded and active.', 'system', 'users', false],
            ],
        ];

        foreach ($items as $role => $rows) {
            $user = User::where('role', $role)->first();
            if (! $user) {
                continue;
            }

            foreach ($rows as [$title, $message, $type, $module, $read]) {
                Notification::updateOrCreate(
                    ['user_id' => $user->id, 'title' => $title],
                    [
                        'message' => $message,
                        'type' => $type,
                        'source_module' => $module,
                        'source_id' => null,
                        'read' => $read,
                    ],
                );
            }
        }
    }
}
