<?php

namespace Database\Seeders;

use App\Models\Sop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Standard Operating Procedure with a short revision history so the SOP banner +
 * History (audit trail) have data. Append-only: each row is a version; latest = current.
 */
class SopSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->value('id');
        $admin = User::where('role', 'Admin')->value('id') ?? $rnd;
        if (! $rnd) {
            return;
        }

        \DB::table('sops')->truncate();

        $versions = [
            [$admin, 'Food Service SOP', "1. Wash hands and sanitize before every prep shift.\n2. Collect ward diet lists by 6:00 AM.\n3. Apportion trays per the active menu cycle.", 21],
            [$rnd,   'Food Service SOP', "1. Wash hands and sanitize before every prep shift.\n2. Collect ward diet lists by 6:00 AM.\n3. Apportion trays per the active menu cycle.\n4. Record served population per service day.", 10],
            [$rnd,   'Food Service SOP', "1. Hygiene: wash + sanitize before prep.\n2. Collect ward diet lists by 6:00 AM.\n3. Apportion trays per the active menu cycle.\n4. Record served population per service day.\n5. Log meal-prep completion and any shortfall before end of shift.", 2],
        ];

        foreach ($versions as [$by, $title, $body, $daysAgo]) {
            Sop::create([
                'title' => $title,
                'body' => $body,
                'created_by' => $by,
                'created_at' => Carbon::now()->subDays($daysAgo),
                'updated_at' => Carbon::now()->subDays($daysAgo),
            ]);
        }
    }
}
