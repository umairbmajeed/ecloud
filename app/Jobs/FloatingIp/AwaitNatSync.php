<?php

namespace App\Jobs\FloatingIp;

use App\Jobs\Job;
use App\Models\V2\FloatingIp;
use App\Support\Sync;
use App\Traits\V2\LoggableModelJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class AwaitNatSync extends Job
{
    use Batchable, LoggableModelJob;

    public $tries = 60;
    public $backoff = 10;

    private $model;

    public function __construct(FloatingIp $floatingIp)
    {
        $this->model = $floatingIp;
    }

    public function handle()
    {
        if ($this->model->sourceNat()->exists() && $this->model->sourceNat->sync->status == Sync::STATUS_FAILED) {
            Log::error('Source NAT in failed sync state, abort', ['id' => $this->model->id, 'nat_id' => $this->model->sourceNat->id]);
            $this->fail(new \Exception("Source NAT '" . $this->model->sourceNat->id . "' in failed sync state"));
            return;
        }

        if ($this->model->destinationNat()->exists() != null && $this->model->destinationNat->sync->status == Sync::STATUS_FAILED) {
            Log::error('Destination NAT in failed sync state, abort', ['id' => $this->model->id, 'nat_id' => $this->model->destinationNat->id]);
            $this->fail(new \Exception("Destination NAT '" . $this->model->destinationNat->id . "' in failed sync state"));
            return;
        }

        if ($this->model->sourceNat()->exists() && $this->model->sourceNat->sync->status != Sync::STATUS_COMPLETE) {
            Log::warning('Source NAT not in sync, retrying in ' . $this->backoff . ' seconds', ['id' => $this->model->id, 'nat_id' => $this->model->sourceNat->id]);
            return $this->release($this->backoff);
        }

        if ($this->model->destinationNat()->exists() && $this->model->destinationNat->sync->status != Sync::STATUS_COMPLETE) {
            Log::warning('Destination NAT not in sync, retrying in ' . $this->backoff . ' seconds', ['id' => $this->model->id, 'nat_id' => $this->model->destinationNat->id]);
            return $this->release($this->backoff);
        }
    }
}
