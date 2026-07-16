<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonNameSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_factories_emit_complete_synchronized_split_names(): void
    {
        $user = User::factory()->make();
        $patient = Patient::factory()->make();

        foreach ([$user, $patient] as $person) {
            $this->assertNotSame('', trim((string) $person->first_name));
            $this->assertNotSame('', trim((string) $person->last_name));
            $this->assertSame($person->first_name.' '.$person->last_name, $person->name);
            $this->assertSame($person->name, $person->display_name);
        }
    }

    public function test_person_factories_can_explicitly_represent_legacy_unsplit_names(): void
    {
        $user = User::factory()->legacyName('Maria Luisa De la Cruz')->make();
        $patient = Patient::factory()->legacyName('Juan Miguel Dela Cruz III')->make();

        foreach ([$user, $patient] as $person) {
            $this->assertNull($person->first_name);
            $this->assertNull($person->last_name);
            $this->assertSame($person->name, $person->display_name);
        }
    }

    public function test_admin_user_seeder_is_idempotent_and_synchronizes_legacy_names(): void
    {
        $this->seed(AdminUserSeeder::class);
        $before = User::query()->orderBy('email')->get()
            ->mapWithKeys(fn (User $user): array => [$user->email => [
                'id' => $user->id,
                'password' => $user->password,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
            ]])->all();

        $this->seed(AdminUserSeeder::class);
        $after = User::query()->orderBy('email')->get()
            ->mapWithKeys(fn (User $user): array => [$user->email => [
                'id' => $user->id,
                'password' => $user->password,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
            ]])->all();

        $this->assertSame($before, $after);
        $this->assertCount(3, $after);
        $this->assertSame([
            'admin@nutriscope.local' => ['Elena', 'Villanueva', 'Elena Villanueva'],
            'fss@nutriscope.local' => ['Maria', 'Santos', 'Maria Santos'],
            'rnd@nutriscope.local' => ['Rosa Mae', 'Dela Cruz', 'Rosa Mae Dela Cruz'],
        ], collect($after)->map(fn (array $account): array => [
            $account['first_name'],
            $account['last_name'],
            $account['name'],
        ])->all());
        foreach ($after as $account) {
            $this->assertNotSame('', trim((string) $account['first_name']));
            $this->assertNotSame('', trim((string) $account['last_name']));
            $this->assertSame($account['first_name'].' '.$account['last_name'], $account['name']);
            $this->assertDoesNotMatchRegularExpression('/^(system|admin|rnd|fss)\b/i', $account['name']);
        }
    }

    public function test_patient_seeder_is_idempotent_by_hospital_number_and_emits_no_audit_noise(): void
    {
        $this->seed(AdminUserSeeder::class);
        $unrelated = activity()->withoutLogs(fn (): Patient => Patient::factory()->create([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'hospital_number' => 'UNRELATED-001',
        ]));

        $this->seed(PatientSeeder::class);
        $before = $this->patientSeederSnapshot();
        $auditCount = AuditActivity::query()->count();

        $this->seed(PatientSeeder::class);
        $after = $this->patientSeederSnapshot();

        $this->assertSame($before, $after);
        $this->assertSame(2, Patient::query()->whereIn('hospital_number', [
            'HN-2026-0042', 'HN-2026-0078',
        ])->count());
        $this->assertTrue(Patient::query()->whereKey($unrelated->id)->exists());
        $this->assertSame(0, $auditCount);
        $this->assertSame(0, AuditActivity::query()->count());
    }

    /** @return array<string, array<string, int|string|null>> */
    private function patientSeederSnapshot(): array
    {
        return Patient::query()
            ->whereIn('hospital_number', ['HN-2026-0042', 'HN-2026-0078'])
            ->orderBy('hospital_number')
            ->get()
            ->mapWithKeys(fn (Patient $patient): array => [$patient->hospital_number => [
                'id' => $patient->id,
                'uuid' => $patient->uuid,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'name' => $patient->name,
                'ncp_count' => NcpRecord::query()->where('patient_id', $patient->id)->count(),
            ]])->all();
    }
}
