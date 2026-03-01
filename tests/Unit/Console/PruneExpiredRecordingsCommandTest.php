<?php

namespace Tests\Unit\Console;

use App\Models\Recording;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneExpiredRecordingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_recordings_past_retention_window(): void
    {
        Storage::fake();

        $tenant = Tenant::factory()->create(['recording_retention_days' => 30]);

        // Old recording — should be deleted
        $old = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'file_path' => 'recordings/old.wav',
        ]);
        $old->created_at = now()->subDays(31);
        $old->save();

        // Recent recording — should be kept
        $recent = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'file_path' => 'recordings/recent.wav',
        ]);

        $this->artisan('nizam:prune-recordings')
            ->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_it_respects_tenant_retention_boundary(): void
    {
        Storage::fake();

        $tenant = Tenant::factory()->create(['recording_retention_days' => 7]);

        // 6-day-old recording — within retention window, must not be deleted
        $within = Recording::factory()->create(['tenant_id' => $tenant->id]);
        $within->created_at = now()->subDays(6);
        $within->save();

        // 8-day-old recording — outside window, must be deleted
        $outside = Recording::factory()->create(['tenant_id' => $tenant->id]);
        $outside->created_at = now()->subDays(8);
        $outside->save();

        $this->artisan('nizam:prune-recordings')->assertExitCode(0);

        $this->assertModelExists($within);
        $this->assertModelMissing($outside);
    }

    public function test_dry_run_does_not_delete_recordings(): void
    {
        Storage::fake();

        $tenant = Tenant::factory()->create(['recording_retention_days' => 1]);

        $recording = Recording::factory()->create(['tenant_id' => $tenant->id]);
        $recording->created_at = now()->subDays(2);
        $recording->save();

        $this->artisan('nizam:prune-recordings', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertModelExists($recording);
    }

    public function test_it_skips_tenants_without_retention_policy(): void
    {
        Storage::fake();

        // Tenant with no retention policy (null)
        $tenant = Tenant::factory()->create(['recording_retention_days' => null]);

        $recording = Recording::factory()->create(['tenant_id' => $tenant->id]);
        $recording->created_at = now()->subDays(365);
        $recording->save();

        $this->artisan('nizam:prune-recordings')->assertExitCode(0);

        $this->assertModelExists($recording);
    }

    public function test_tenant_option_restricts_pruning_to_single_tenant(): void
    {
        Storage::fake();

        $tenantA = Tenant::factory()->create(['recording_retention_days' => 1]);
        $tenantB = Tenant::factory()->create(['recording_retention_days' => 1]);

        $recordingA = Recording::factory()->create(['tenant_id' => $tenantA->id]);
        $recordingA->created_at = now()->subDays(2);
        $recordingA->save();

        $recordingB = Recording::factory()->create(['tenant_id' => $tenantB->id]);
        $recordingB->created_at = now()->subDays(2);
        $recordingB->save();

        // Prune only tenant A
        $this->artisan('nizam:prune-recordings', ['--tenant' => $tenantA->id])
            ->assertExitCode(0);

        $this->assertModelMissing($recordingA);
        $this->assertModelExists($recordingB);
    }

    public function test_it_deletes_backing_file_from_storage(): void
    {
        $disk = Storage::fake();

        $tenant = Tenant::factory()->create(['recording_retention_days' => 1]);

        $filePath = 'recordings/to-delete.wav';
        $disk->put($filePath, 'fake audio data');
        $this->assertTrue($disk->exists($filePath));

        $recording = Recording::factory()->create([
            'tenant_id' => $tenant->id,
            'file_path' => $filePath,
        ]);
        $recording->created_at = now()->subDays(2);
        $recording->save();

        $this->artisan('nizam:prune-recordings')->assertExitCode(0);

        $this->assertModelMissing($recording);
        $this->assertFalse($disk->exists($filePath));
    }

    public function test_returns_success_when_no_tenants_have_retention_policy(): void
    {
        Tenant::factory()->count(3)->create(['recording_retention_days' => null]);

        $this->artisan('nizam:prune-recordings')->assertExitCode(0);
    }
}
