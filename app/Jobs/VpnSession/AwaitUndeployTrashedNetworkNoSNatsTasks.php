<?php

namespace App\Jobs\VpnSession;

use App\Jobs\Job;
use App\Models\V2\Instance;
use App\Models\V2\Task;
use App\Models\V2\VpnSession;
use App\Support\Sync;
use App\Traits\V2\LoggableModelJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class AwaitUndeployTrashedNetworkNoSNatsTasks extends Job
{
    use Batchable, LoggableModelJob;

    public $tries = 30;
    public $backoff = 5;

    private Task $task;
    private VpnSession $model;

    public function __construct(Task $task, VpnSession $vpnSession)
    {
        $this->task = $task;
        $this->model = $vpnSession;
    }

    public function handle()
    {
        if (empty($this->task->data[UndeployTrashedNetworkNoSNats::TASK_WAIT_DATA_KEY])) {
            Log::debug('No tasks to await, skipping');
            return;
        }

        foreach ($this->task->data[UndeployTrashedNetworkNoSNats::TASK_WAIT_DATA_KEY] as $taskID) {
            Log::warning("WAITING ON TASKID $taskID");
            $task = Task::findOrFail($taskID);
            Log::warning("FOUND TASK ID {$task->id}");
            if ($task->status == Task::STATUS_FAILED) {
                Log::error(get_class($this) . ': Task in failed state, abort', ['id' => $this->model->id, 'task_id' => $task->id]);
                $this->fail(new \Exception("Task {$task->id} in failed state, abort"));
                return;
            }

            if ($task->status != Task::STATUS_COMPLETE) {
                Log::warning(get_class($this) . ': Task not complete, retrying in ' . $this->backoff . ' seconds', ['id' => $this->model->id, 'task_id' => $task->id]);
                $this->release($this->backoff);
                return;
            }
        }
    }
}
