<?php

namespace Tests\Feature\Jobs;

use App\Enums\TokenStatus;
use App\Jobs\PurgeExpiredTokens;
use App\Jobs\PurgeSoftDeletedObjects;
use App\Jobs\RecalculateBucketUsage;
use App\Jobs\ScanObjectForViruses;
use App\Models\ApiToken;
use App\Models\Bucket;
use App\Models\ObjectVersion;
use App\Models\StoredObject;
use App\Services\VirusScan\ScanResult;
use App\Services\VirusScan\VirusScannerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackgroundJobsTest extends TestCase
{
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = sys_get_temp_dir().'/fileserver_jobs_'.uniqid();
        config(['fileserver.storage_root' => $this->storageRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);
        parent::tearDown();
    }

    public function test_scan_object_for_viruses_quarantines_infected_files(): void
    {
        $bucket = Bucket::factory()->create(['storage_path' => 'b1']);
        File::ensureDirectoryExists($this->storageRoot.'/b1');
        file_put_contents($this->storageRoot.'/b1/eicar.txt', 'fake-signature-content');

        $object = StoredObject::factory()->create([
            'bucket_id' => $bucket->id,
            'key' => 'eicar.txt',
        ]);

        $this->app->bind(VirusScannerInterface::class, fn () => new class implements VirusScannerInterface
        {
            public function scan(string $absolutePath): ScanResult
            {
                return ScanResult::infected('Eicar-Test-Signature');
            }

            public function isEnabled(): bool
            {
                return true;
            }
        });

        (new ScanObjectForViruses($bucket->id, 'eicar.txt'))->handle(
            app(VirusScannerInterface::class),
            app(\App\Services\Storage\StorageManager::class),
            app(\App\Services\AuditLogService::class),
        );

        $this->assertFalse(is_file($this->storageRoot.'/b1/eicar.txt'));
        $this->assertSoftDeleted('stored_objects', ['id' => $object->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'object.quarantined', 'object_key' => 'eicar.txt']);
    }

    public function test_scan_object_for_viruses_is_noop_when_scanner_disabled(): void
    {
        $bucket = Bucket::factory()->create(['storage_path' => 'b2']);
        File::ensureDirectoryExists($this->storageRoot.'/b2');
        file_put_contents($this->storageRoot.'/b2/clean.txt', 'hello');

        StoredObject::factory()->create(['bucket_id' => $bucket->id, 'key' => 'clean.txt']);

        (new ScanObjectForViruses($bucket->id, 'clean.txt'))->handle(
            app(VirusScannerInterface::class), // NullVirusScanner by default
            app(\App\Services\Storage\StorageManager::class),
            app(\App\Services\AuditLogService::class),
        );

        $this->assertTrue(is_file($this->storageRoot.'/b2/clean.txt'));
    }

    public function test_purge_expired_tokens_revokes_only_expired_active_tokens(): void
    {
        $expired = ApiToken::factory()->create(['status' => TokenStatus::Active, 'expires_at' => now()->subDay()]);
        $future = ApiToken::factory()->create(['status' => TokenStatus::Active, 'expires_at' => now()->addDay()]);
        $noExpiry = ApiToken::factory()->create(['status' => TokenStatus::Active, 'expires_at' => null]);

        (new PurgeExpiredTokens)->handle();

        $this->assertSame('revoked', $expired->fresh()->status->value);
        $this->assertSame('active', $future->fresh()->status->value);
        $this->assertSame('active', $noExpiry->fresh()->status->value);
    }

    public function test_purge_soft_deleted_objects_removes_stale_rows_and_version_files(): void
    {
        $bucket = Bucket::factory()->create(['storage_path' => 'b3']);
        File::ensureDirectoryExists($this->storageRoot.'/b3/.versions/x');
        file_put_contents($this->storageRoot.'/b3/.versions/x/v1__old.txt', 'old content');

        $stale = StoredObject::factory()->create(['bucket_id' => $bucket->id, 'key' => 'old.txt']);
        ObjectVersion::factory()->create([
            'stored_object_id' => $stale->id,
            'storage_relative_path' => '.versions/x/v1__old.txt',
        ]);
        $stale->delete();
        $stale->forceFill(['deleted_at' => now()->subDays(60)])->saveQuietly();

        $recent = StoredObject::factory()->create(['bucket_id' => $bucket->id, 'key' => 'recent.txt']);
        $recent->delete(); // deleted "now", within retention

        (new PurgeSoftDeletedObjects)->handle(app(\App\Services\Storage\StorageManager::class));

        $this->assertDatabaseMissing('stored_objects', ['id' => $stale->id]);
        $this->assertFalse(is_file($this->storageRoot.'/b3/.versions/x/v1__old.txt'));
        $this->assertSoftDeleted('stored_objects', ['id' => $recent->id]);
    }

    public function test_recalculate_bucket_usage_caches_the_live_sum(): void
    {
        $bucket = Bucket::factory()->create();
        StoredObject::factory()->create(['bucket_id' => $bucket->id, 'size_bytes' => 1000]);
        StoredObject::factory()->create(['bucket_id' => $bucket->id, 'size_bytes' => 2500]);

        (new RecalculateBucketUsage($bucket->id))->handle(app(\App\Services\QuotaService::class));

        $this->assertSame(3500, Cache::get(RecalculateBucketUsage::CACHE_PREFIX.$bucket->id));
    }
}
