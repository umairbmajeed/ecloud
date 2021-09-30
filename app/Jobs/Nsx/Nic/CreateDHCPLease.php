<?php

namespace App\Jobs\Nsx\Nic;

use App\Jobs\Job;
use App\Models\V2\Nic;
use App\Traits\V2\LoggableModelJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class CreateDHCPLease extends Job
{
    use Batchable, LoggableModelJob;

    private $model;

    public function __construct(Nic $nic)
    {
        $this->model = $nic;
    }

    public function handle()
    {
        Log::info(get_class($this) . ' : Started', ['id' => $this->model->id]);

        $nic = $this->model;

        if ($nic->ip_address) {
            Log::info("DHCP IP address already assigned, skipping");
            return true;
        }

        $network = $nic->network;
        $router = $nic->network->router;
        $nsxService = $router->availabilityZone->nsxService();
        /**
         * Get DHCP static bindings to determine used IP addresses on the network
         * @see https://185.197.63.88/policy/api_includes/method_ListSegmentDhcpStaticBinding.html
         */
        $cursor = null;
        $assignedIpsNsx = collect();
        do {
            $response = $nsxService->get('/policy/api/v1/infra/tier-1s/' . $router->id . '/segments/' . $network->id . '/dhcp-static-binding-configs?cursor=' . $cursor);
            $response = json_decode($response->getBody()->getContents());
            foreach ($response->results as $dhcpStaticBindingConfig) {
                $assignedIpsNsx->push($dhcpStaticBindingConfig->ip_address);
            }
            $cursor = $response->cursor ?? null;
        } while (!empty($cursor));

        $ipAddress = $nic->assignIpAddress($assignedIpsNsx->toArray());

        $nic->refresh();

        $nsxService->put(
            '/policy/api/v1/infra/tier-1s/' . $router->id . '/segments/' . $network->id
            . '/dhcp-static-binding-configs/' . $nic->id,
            [
                'json' => [
                    'resource_type' => 'DhcpV4StaticBindingConfig',
                    'mac_address' => $nic->mac_address,
                    'ip_address' => $ipAddress->ip_address
                ]
            ]
        );

        Log::info('DHCP static binding created for ' . $nic->id . ' (' . $nic->mac_address . ') with IP ' . $ipAddress->ip_address);

        Log::info(get_class($this) . ' : Finished', ['id' => $nic->id]);
    }
}
