<?php

namespace App\Jobs\Sync\VpnEndpoint;

use App\Jobs\Job;
use App\Jobs\Nsx\VpnEndpoint\Undeploy;
use App\Models\V2\Task;
use App\Models\V2\VpnEndpoint;
use App\Traits\V2\LoggableTaskJob;
use App\Traits\V2\TaskableBatch;

class Delete extends Job
{
    use TaskableBatch, LoggableTaskJob;

    private Task $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function handle()
    {
        $this->deleteTaskBatch([
            [
                new Undeploy($this->task->resource),
            ]
        ])->dispatch();
    }
}
