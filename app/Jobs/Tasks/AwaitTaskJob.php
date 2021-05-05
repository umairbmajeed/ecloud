<?php

namespace App\Jobs\Tasks;

use App\Jobs\Job;
use App\Models\V2\Task;
use App\Traits\V2\JobModel;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class AwaitTaskJob extends Job
{
    use Batchable, JobModel;

    public $tries;
    public $backoff;

    private Task $task;

    public function __construct(Task $task, $tries = 60, $backoff = 5)
    {
        $this->task = $task;
        $this->tries = $tries;
        $this->backoff = $backoff;
    }

    public function handle()
    {
        if ($this->task->status == Task::STATUS_FAILED) {
            $this->fail(new \Exception("Task '" . $this->task->id . "' in failed state"));
            return;
        }

        if ($this->task->status == Task::STATUS_INPROGRESS) {
            Log::warning($this->task->id . ' in-progress, retrying in ' . $this->backoff . ' seconds', ['id' => $this->task->id]);
            return $this->release($this->backoff);
        }
    }
}
