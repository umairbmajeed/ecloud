<?php

namespace App\Jobs\Instance\Deploy;

use App\Jobs\Job;
use App\Models\V2\Instance;
use App\Models\V2\Vpc;
use Illuminate\Support\Facades\Log;

class UpdateNetworkAdapter extends Job
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @see https://gitlab.devops.ukfast.co.uk/ukfast/api.ukfast/ecloud/-/issues/327
     */
    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['data' => $this->data]);

        $instance = Instance::findOrFail($this->data['instance_id']);
        $vpc = Vpc::findOrFail($this->data['vpc_id']);

        if (empty($instance->image->vm_template_name)) {
            Log::info('Skipped UpdateNetworkAdapter for instance ' . $this->data['instance_id'] . ': no vm template found');
            return;
        }

        foreach ($instance->nics as $nic) {
            $instance->availabilityZone->kingpinService()->put(
                '/api/v2/vpc/' . $vpc->id . '/instance/' . $instance->id . '/nic/' . $nic->mac_address . '/connect',
                [
                    'json' => [
                        'networkId' => $nic->network_id,
                    ],
                ]
            );
        }

        Log::info(get_class($this) . ' : Finished', ['data' => $this->data]);
    }
}
