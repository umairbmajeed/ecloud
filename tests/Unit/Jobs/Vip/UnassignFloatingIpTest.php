<?php

namespace Tests\Unit\Jobs\Vip;

use App\Events\V2\Task\Created;
use App\Jobs\Tasks\FloatingIp\Unassign;
use App\Jobs\Vip\UnassignFloatingIp;
use App\Models\V2\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Tests\Mocks\Resources\VipMock;
use Tests\TestCase;

class UnassignFloatingIpTest extends TestCase
{
    use VipMock;

    public function setUp(): void
    {
        parent::setUp();
        Event::fake([JobFailed::class, Created::class, JobProcessed::class]);
        $this->vip()->assignClusterIp();
    }

    public function testUnassignFloatingIpSuccess()
    {
        $this->floatingIp()->resource()->associate($this->vip()->ipAddress);
        $this->floatingIp()->save();

        $task = $this->createSyncDeleteTask($this->vip());

        dispatch(new UnassignFloatingIp($task));

        Event::assertDispatched(Created::class, function ($event) {
            return $event->model->name == Unassign::$name;
        });

        $task->refresh();

        $this->assertNotNull($task->data['task.' . Unassign::$name . '.id']);

        // Mark the fip un-assign task as completed
        $assignTask = Event::dispatched(Created::class, function ($event) {
            return $event->model->name == Unassign::$name;
        })->first()[0];

        $assignTask->model->setAttribute('completed', true)->saveQuietly();

        dispatch(new UnassignFloatingIp($task));

        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });
    }

    public function testUnassignFloatingIpTaskNotCompleteIsReleased()
    {
        $this->floatingIp()->resource()->associate($this->vip()->ipAddress);
        $this->floatingIp()->save();

        $floatingIpUnassignTask = Model::withoutEvents(function () {
            $task = new Task([
                'id' => 'task-1',
                'completed' => false,
                'name' => Unassign::$name,
            ]);
            $task->resource()->associate($this->floatingIp());
            $task->save();
            return $task;
        });

        $task = $this->createSyncDeleteTask(
            $this->vip(),
            ['task.' . Unassign::$name . '.id' => $floatingIpUnassignTask->id]
        );

        dispatch(new UnassignFloatingIp($task));

        Event::assertNotDispatched(Created::class);

        Event::assertDispatched(JobProcessed::class, function ($event) {
            return $event->job->isReleased();
        });
    }

    public function testNoFloatingIpAssignedSkips()
    {
        $task = $this->createSyncDeleteTask($this->vip());

        dispatch(new UnassignFloatingIp($task));

        Event::assertNotDispatched(Created::class);

        Event::assertDispatched(JobProcessed::class, function ($event) {
            return !$event->job->isReleased();
        });
    }
}