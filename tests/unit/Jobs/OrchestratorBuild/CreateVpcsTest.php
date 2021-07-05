<?php
namespace Tests\unit\Jobs\OrchestratorBuild;

use App\Events\V2\Task\Created;
use App\Jobs\OrchestratorBuild\CreateVpcs;
use App\Models\V2\OrchestratorBuild;
use App\Models\V2\OrchestratorConfig;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateVpcsTest extends TestCase
{
    protected $job;

    protected OrchestratorConfig $orchestratorConfig;

    protected OrchestratorBuild $orchestratorBuild;

    public function setUp(): void
    {
        parent::setUp();
        $this->orchestratorConfig = factory(OrchestratorConfig::class)->create();

        $this->orchestratorBuild = factory(OrchestratorBuild::class)->make();
        $this->orchestratorBuild->orchestratorConfig()->associate($this->orchestratorConfig);
        $this->orchestratorBuild->save();
    }

    public function testNoVpcDataSkips()
    {
        $this->orchestratorConfig->data = null;
        $this->orchestratorConfig->save();

        Event::fake([JobFailed::class, JobProcessed::class]);

        dispatch(new CreateVpcs($this->orchestratorBuild));

        Event::assertNotDispatched(JobFailed::class);
        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });    }

    public function testVpcAlreadyExistsSkips()
    {
        Event::fake([JobFailed::class, JobProcessed::class, Created::class]);

        $this->orchestratorBuild->updateState('vpc', 0, 'vpc-testing');

        dispatch(new CreateVpcs($this->orchestratorBuild));

        Event::assertNotDispatched(JobFailed::class);
        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });

        $this->orchestratorBuild->refresh();

        $this->assertNotNull($this->orchestratorBuild->state['vpc']);

        // 2 VPC's in the build data, one already exists so only one more should be created
        $this->assertEquals(2, count($this->orchestratorBuild->state['vpc']));
    }

    public function testSuccess()
    {
        Event::fake([JobFailed::class, JobProcessed::class, Created::class]);

        dispatch(new CreateVpcs($this->orchestratorBuild));

        Event::assertNotDispatched(JobFailed::class);
        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });

        Event::assertDispatched(Created::class);

        $this->orchestratorBuild->refresh();

        $this->assertNotNull($this->orchestratorBuild->state['vpc']);

        $this->assertEquals(2, count($this->orchestratorBuild->state['vpc']));
    }
}