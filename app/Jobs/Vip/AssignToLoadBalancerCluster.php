<?php

namespace App\Jobs\Vip;

use App\Jobs\TaskJob;
use UKFast\Admin\Loadbalancers\AdminClient;
use UKFast\Admin\Loadbalancers\Entities\Vip;

class AssignToLoadBalancerCluster extends TaskJob
{
    /**
     * Assign the VIP to the cluster via the load balancer API
     * @see https://gitlab.devops.ukfast.co.uk/ukfast/api.ukfast/loadbalancers/-/blob/master/openapi.yaml
     * @return void
     */
    public function handle()
    {
        $vip = $this->task->resource;

        $adminClient = app()->make(AdminClient::class)->setResellerId($vip->loadbalancer->getResellerId());

        $vipEntity = app()->make(Vip::class);
        // convert to CIDR format /32 is 1 IP address in subnet
        $vipEntity->internalCidr = $vip->ipAddress->getIPAddress() . '/32';

        if ($vip->ipAddress->floatingIp()->exists()) {
            $vipEntity->externalCidr = $vip->ipAddress->floatingIp->getIPAddress(). '/32';
        }

        // TODO: Do we need to set this optional parameter? and what to? The created NICs have mac addresses? but we will have multiple.
//        $vip->macAddress =

        $response = $adminClient->vips()->createEntity($vip->loadbalancer->config_id, $vipEntity);

        $this->info('VIP was assigned to the load balancer cluster with ID: ' . $response->getId());
    }
}
