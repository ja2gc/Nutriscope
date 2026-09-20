<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('patient_code', 12)->nullable()->after('uuid');
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->string('patient_code_snapshot', 12)->nullable()->after('patient_display_name_snapshot');
        });

        DB::table('patients')->select('id')->orderBy('id')->chunkById(500, function ($patients): void {
            foreach ($patients as $patient) {
                DB::table('patients')->where('id', $patient->id)->update([
                    'patient_code' => $this->uniquePatientCode(),
                ]);
            }
        });

        DB::table('activity_log')
            ->whereNotNull('root_patient_id')
            ->select('id', 'root_patient_id')
            ->orderBy('id')
            ->chunkById(500, function ($activities): void {
                $codes = DB::table('patients')
                    ->whereIn('id', $activities->pluck('root_patient_id')->unique()->all())
                    ->pluck('patient_code', 'id');

                foreach ($activities as $activity) {
                    $code = $codes->get($activity->root_patient_id);
                    if (is_string($code)) {
                        DB::table('activity_log')->where('id', $activity->id)->update([
                            'patient_code_snapshot' => $code,
                        ]);
                    }
                }
            });

        Schema::table('patients', function (Blueprint $table): void {
            $table->string('patient_code', 12)->nullable(false)->change();
            $table->unique('patient_code');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropColumn('patient_code_snapshot');
        });

        Schema::table('patients', function (Blueprint $table): void {
            $table->dropUnique(['patient_code']);
            $table->dropColumn('patient_code');
        });
    }

    private function uniquePatientCode(): string
    {
        do {
            $code = 'NS-'.$this->segment().'-'.$this->segment();
        } while (DB::table('patients')->where('patient_code', $code)->exists());

        return $code;
    }

    private function segment(): string
    {
        $segment = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < 4; $index++) {
            $segment .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $segment;
    }
};
