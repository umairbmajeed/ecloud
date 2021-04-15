<?php
namespace Tests\unit\Listeners\V2\Instance;

use App\Models\V2\BillingMetric;
use App\Models\V2\Sync;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class UpdateLicenseBillingTest extends TestCase
{
    use DatabaseMigrations;

    private $sync;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testInstertLicenseBilling()
    {
        $this->instance()->vcpu_cores = 1;
        $this->instance()->platform = 'Windows';

        Sync::withoutEvents(function() {
            $this->sync = new Sync([
                'id' => 'sync-1',
                'completed' => true,
            ]);
            $this->sync->resource()->associate($this->instance());
        });

        $updateLicenseBillingListener = new \App\Listeners\V2\Instance\UpdateLicenseBilling();
        $updateLicenseBillingListener->handle(new \App\Events\V2\Sync\Updated($this->sync));

        $vcpuMetric = BillingMetric::getActiveByKey($this->instance(), 'license.windows');
        $this->assertNotNull($vcpuMetric);
        $this->assertEquals(1, $vcpuMetric->value);
    }

    public function testUpdateLicenseChangeBilling()
    {
        $originalVcpuMetric = factory(BillingMetric::class)->create([
            'id' => 'bm-test1',
            'resource_id' => $this->instance()->id,
            'vpc_id' => $this->vpc()->id,
            'key' => 'license.windows',
            'value' => 1,
            'start' => '2020-07-07T10:30:00+01:00',
        ]);

        $this->instance()->vcpu_cores = 5;
        $this->instance()->platform = 'Windows';

        Sync::withoutEvents(function() {
            $this->sync = new Sync([
                'id' => 'sync-1',
                'completed' => true,
            ]);
            $this->sync->resource()->associate($this->instance());
        });

        $updateLicenseBillingListener = new \App\Listeners\V2\Instance\UpdateLicenseBilling();
        $updateLicenseBillingListener->handle(new \App\Events\V2\Sync\Updated($this->sync));

        $vcpuMetric = BillingMetric::getActiveByKey($this->instance(), 'license.windows');
        $this->assertNotNull($vcpuMetric);
        // round up to the closes 2 core pack 5/2 = 3
        $this->assertEquals(3, $vcpuMetric->value);

        // Check existing metric was ended
        $originalVcpuMetric->refresh();
        $this->assertNotNull($originalVcpuMetric->end);
    }
}
