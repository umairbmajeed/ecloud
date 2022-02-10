<?php

namespace Tests\unit\Jobs\FloatingIp;

use App\Jobs\FloatingIp\AllocateRdnsHostname;
use App\Models\V2\FloatingIp;
use App\Models\V2\Task;
use App\Support\Sync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use UKFast\Admin\SafeDNS\AdminClient;
use UKFast\Admin\SafeDNS\AdminRecordClient;
use UKFast\SDK\SafeDNS\Entities\Record;

class AllocateRdnsHostnameTest extends TestCase
{
    protected FloatingIp $floatingIp;
    protected $mockRecordAdminClient;
    private Task $task;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockRecordAdminClient = \Mockery::mock(AdminRecordClient::class);

        $mockSafednsAdminClient = \Mockery::mock(AdminClient::class);

        $mockSafednsAdminClient->shouldReceive('records')->andReturn(
            $this->mockRecordAdminClient
        );
        app()->bind(AdminClient::class, function () use ($mockSafednsAdminClient) {
            return $mockSafednsAdminClient;
        });

        app()->bind(AdminRecordClient::class, function () {
            return $this->mockRecordAdminClient;
        });

        $this->mockRecordAdminClient->shouldReceive('getPage')->andReturnUsing(function () {
            $mockRecord = \Mockery::mock(Record::class);
            $mockRecord->shouldReceive('totalPages')->andReturn(1);
            $mockRecord->shouldReceive('getItems')->andReturn(
                new Collection([
                    new \UKFast\SDK\SafeDNS\Entities\Record(
                        [
                            "id" => 10015521,
                            "zone" => "1.2.3.in-addr.arpa",
                            "name" => "1.2.3.4.in-addr.arpa",
                            "type" => "PTR",
                            "content" => "198.172.168.0.svrlist.co.uk",
                            "updated_at" => "1970-01-01T01:00:00+01:00",
                            "ttl" => 86400,
                            "priority" => null
                        ]
                    )
                ])
            );

            return $mockRecord;
        });

        $this->mockRecordAdminClient->expects('update')->andReturnTrue();
    }

    public function testRdnsAllocated()
    {
        Model::withoutEvents(function () {
            $this->floatingIp = factory(FloatingIp::class)->create([
                'id' => 'fip-test',
                'vpc_id' => $this->vpc()->id,
                'availability_zone_id' => $this->availabilityZone()->id,
                'ip_address' => '10.0.0.1',
            ]);
        });
        $this->task = Task::withoutEvents(function () {
            $task = new Task([
                'id' => 'sync-1',
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $task->resource()->associate($this->floatingIp);
            $task->save();
            return $task;
        });

        Event::fake([JobFailed::class, JobProcessed::class]);

        dispatch(new AllocateRdnsHostname($this->task));

        Event::assertNotDispatched(JobFailed::class);
        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });

        $this->floatingIp->refresh();

        $this->assertEquals($this->floatingIp->rdns_hostname, config('defaults.floating-ip.rdns.default_hostname'));
    }
}
