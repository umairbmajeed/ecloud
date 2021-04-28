<?php

namespace App\Jobs\Sync\NetworkPolicy;

use App\Jobs\Job;
use App\Jobs\NetworkPolicy\DeleteChildResources;
use App\Jobs\Nsx\FirewallPolicy\Undeploy;
use App\Jobs\Nsx\FirewallPolicy\UndeployCheck;
use App\Jobs\Sync\Completed;
use App\Jobs\Sync\Delete as SyncDelete;
use App\Models\V2\NetworkPolicy;
use App\Models\V2\Sync;
use App\Traits\V2\SyncableBatch;
use Illuminate\Support\Facades\Log;

class Delete extends Job
{
    use SyncableBatch;

    private $sync;

    public function __construct(Sync $sync)
    {
        $this->sync = $sync;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->sync->id, 'resource_id' => $this->sync->resource->id]);

        $this->deleteSyncBatch([
            [
                new Undeploy($this->sync->resource),
                new UndeployCheck($this->sync->resource),
            ]
        ])->dispatch();


        $jobs = [
            new DeleteChildResources($this->model),
            new \App\Jobs\Nsx\NetworkPolicy\Undeploy($this->model),
            new \App\Jobs\Nsx\NetworkPolicy\UndeployCheck($this->model),
            new \App\Jobs\Nsx\NetworkPolicy\SecurityGroup\Undeploy($this->model),
            new \App\Jobs\Nsx\NetworkPolicy\SecurityGroup\UndeployCheck($this->model),
            new Completed($this->model),
            new SyncDelete($this->model),
        ];
        dispatch(array_shift($jobs)->chain($jobs));

        Log::info(get_class($this) . ' : Finished', ['id' => $this->sync->id, 'resource_id' => $this->sync->resource->id]);
    }
}
