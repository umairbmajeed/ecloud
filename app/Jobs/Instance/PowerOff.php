<?php

namespace App\Jobs\Instance;

use App\Jobs\Job;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use Illuminate\Support\Facades\Log;

class PowerOff extends Job
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['data' => $this->data]);

        $instance = Instance::withTrashed()->findOrFail($this->data['instance_id']);
        $vpc = Vpc::findOrFail($this->data['vpc_id']);
        $response = $instance->availabilityZone->kingpinService()->delete(
            '/api/v2/vpc/' . $vpc->id . '/instance/' . $instance->id . '/power'
        );
        $instance->setSyncCompleted();

        // Catch already deleted
        $responseJson = json_decode($response->getBody()->getContents());
        if (isset($responseJson->ExceptionType) && $responseJson->ExceptionType == 'UKFast.VimLibrary.Exception.EntityNotFoundException') {
            Log::info('Attempted to power off, but entity was not found, skipping.');
            return;
        }

        Log::info(get_class($this) . ' : Finished', ['data' => $this->data]);
    }
}
