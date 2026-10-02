<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\StoredObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeDemographicCensusPdfsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_targets_without_deleting_and_execute_removes_only_census_artifacts(): void
    {
        Storage::fake('report_cache');
        Storage::fake('private_uploads');
        Storage::fake('public');
        $actor = User::factory()->rnd()->create();
        $official = app(StoredObjectStorage::class)->storeBytes("%PDF-1.4\nlegacy census\n%%EOF", 'application/pdf', 'pdf', 'report', 'census.pdf');
        $census = Report::factory()->create([
            'user_id' => $actor->id,
            'type' => 'demographic_census',
            'status' => 'archived',
            'official_file_stored_object_id' => $official->id,
            'cache_path' => 'reports/legacy-census.pdf',
            'file_path' => 'reports/legacy-census-public.pdf',
        ]);
        Storage::disk('report_cache')->put($census->cache_path, 'cached');
        Storage::disk('public')->put($census->file_path, 'public');
        $other = Report::factory()->create(['user_id' => $actor->id, 'type' => 'procurement_pack']);

        $this->artisan('reports:purge-demographic-census-pdfs')->assertSuccessful();
        $this->assertDatabaseHas('reports', ['id' => $census->id]);
        Storage::disk('private_uploads')->assertExists($official->object_key);

        $this->artisan('reports:purge-demographic-census-pdfs --execute')->assertSuccessful();
        $this->assertDatabaseMissing('reports', ['id' => $census->id]);
        $this->assertDatabaseHas('reports', ['id' => $other->id]);
        $this->assertDatabaseMissing('stored_objects', ['id' => $official->id]);
        Storage::disk('private_uploads')->assertMissing($official->object_key);
        Storage::disk('report_cache')->assertMissing($census->cache_path);
        Storage::disk('public')->assertMissing($census->file_path);
        $this->artisan('reports:purge-demographic-census-pdfs --execute')->assertSuccessful();
    }

    public function test_shared_official_file_blocks_cleanup_before_deleting_anything(): void
    {
        Storage::fake('private_uploads');
        $actor = User::factory()->rnd()->create();
        $official = app(StoredObjectStorage::class)->storeBytes("%PDF-1.4\nshared\n%%EOF", 'application/pdf', 'pdf', 'report', 'shared.pdf');
        $census = Report::factory()->create(['user_id' => $actor->id, 'type' => 'demographic_census', 'official_file_stored_object_id' => $official->id]);
        $other = Report::factory()->create(['user_id' => $actor->id, 'type' => 'procurement_pack', 'official_file_stored_object_id' => $official->id]);

        $this->artisan('reports:purge-demographic-census-pdfs --execute')->assertExitCode(1);

        $this->assertDatabaseHas('reports', ['id' => $census->id]);
        $this->assertDatabaseHas('reports', ['id' => $other->id]);
        Storage::disk('private_uploads')->assertExists($official->object_key);
    }
}
