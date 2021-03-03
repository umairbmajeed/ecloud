<?php

namespace App\Traits\V2;

use App\Models\V2\FirewallPolicy;
use App\Models\V2\FirewallRule;
use App\Models\V2\FirewallRulePort;
use App\Models\V2\HostGroup;
use App\Models\V2\NetworkPolicy;
use App\Models\V2\Sync;
use App\Models\V2\Volume;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

trait Syncable
{
    public function delete()
    {
        $class = explode('\\', __CLASS__);
        $class = 'App\\Jobs\\Sync\\' . end($class) . '\\Delete';
        if (!class_exists($class)) {
            throw new \Exception('Syncable "Delete" job not found for ' . __CLASS__);
        }

        if (!$this->createSync()) {
            return false;
        }

        dispatch(new $class($this));

        return true;
    }

    public function syncDelete()
    {
        $response = parent::delete();
        if (!$response) {
            Log::error(get_class($this) . ' : Failed to delete', ['resource_id' => $this->id]);
            return $response;
        }
        Log::info(get_class($this) . ' : Deleted', ['resource_id' => $this->id]);
    }

    public function save(array $options = [])
    {
        // Only do this for Firewall's & Volumes at the moment
        if (!in_array(__CLASS__, [
            FirewallPolicy::class,
            Volume::class,
            NetworkPolicy::class,
            HostGroup::class,
        ])) {
            return parent::save($options);
        }

        $originalValues = $this->getOriginal();
        $response = parent::save($options);
        if (!$response) {
            Log::error(get_class($this) . ' : Failed to save', ['resource_id' => $this->id]);
            return $response;
        }

        $class = explode('\\', __CLASS__);
        $class = 'App\\Jobs\\Sync\\' . end($class) . '\\Save';
        if (!class_exists($class)) {
            throw new \Exception('Syncable "Save" job not found for ' . __CLASS__);
        }

        if (!$this->createSync()) {
            return false;
        }

        dispatch(new $class($this, $originalValues));

        return $response;
    }

    public function createSync()
    {
        Log::info(get_class($this) . ' : Creating new sync - Started', [
            'resource_id' => $this->id,
        ]);

        if ($this->getStatus() === 'in-progress') {
            Log::info(get_class($this) . ' : Failed creating new sync on ' . __CLASS__ . ' with an outstanding sync', [
                'resource_id' => $this->id,
            ]);
            return false;
        }

        $sync = app()->make(Sync::class);
        $sync->resource_id = $this->id;
        $sync->completed = false;
        $sync->save();
        Log::info(get_class($this) . ' : Creating new sync - Finished', [
            'resource_id' => $this->id,
        ]);

        return $sync;
    }

    public function getStatus()
    {
        if (!$this->syncs()->count()) {
            return 'complete';
        }
        if ($this->getSyncFailed()) {
            return 'failed';
        }
        if ($this->syncs()->latest()->first()->completed) {
            return 'complete';
        }
        return 'in-progress';
    }

    public function syncs()
    {
        return $this->hasMany(Sync::class, 'resource_id', 'id');
    }

    public function getSyncFailed()
    {
        if (!$this->syncs()->count()) {
            return false;
        }
        return $this->syncs()->latest()->first()->failure_reason !== null;
    }

    public function setSyncCompleted()
    {
        Log::info(get_class($this) . ' : Setting Sync to completed - Started', ['resource_id' => $this->id]);
        if (!$this->syncs()->count()) {
            Log::info(
                get_class($this) . ' : Setting Sync to completed - Not found, skipped',
                ['resource_id' => $this->id]
            );
            return;
        }
        $sync = $this->syncs()->latest()->first();
        $sync->completed = true;
        $sync->save();
        Log::info(get_class($this) . ' : Setting Sync to completed - Finished', ['resource_id' => $this->id]);
    }

    public function setSyncFailureReason($value)
    {
        Log::info(get_class($this) . ' : Setting Sync to failed - Started', ['resource_id' => $this->id]);
        if (!$this->syncs()->count()) {
            return;
        }
        $sync = $this->syncs()->latest()->first();
        $sync->failure_reason = $value;
        $sync->save();
        Log::debug(get_class($this), ['reason' => $value]);
        Log::info(get_class($this) . ' : Setting Sync to failed - Finished', ['resource_id' => $this->id]);
    }

    public function getSyncFailureReason()
    {
        if (!$this->syncs()->count()) {
            return null;
        }
        return $this->syncs()->latest()->first()->failure_reason;
    }

    /**
     * TODO :- Come up with a nicer way to do this as this is disgusting!
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSyncError()
    {
        return \Illuminate\Http\JsonResponse::create(
            [
                'errors' => [
                    [
                        'title' => 'Resource unavailable',
                        'detail' => 'The specified resource is being modified and is unavailable at this time',
                        'status' => Response::HTTP_CONFLICT,
                    ],
                ],
            ],
            Response::HTTP_CONFLICT
        );
    }
}
