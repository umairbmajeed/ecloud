<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\ExampleEvent' => [
            'App\Listeners\ExampleListener',
        ],
        ///////////////////////////////////////////////////////////////////////////////////////////////
        // V1
        ///////////////////////////////////////////////////////////////////////////////////////////////
        'App\Events\V1\ApplianceParameterDeletedEvent' => [
            'App\Listeners\V1\ApplianceParameterDeletedListener',
        ],
        'App\Events\V1\ApplianceVersionDeletedEvent' => [
            'App\Listeners\V1\ApplianceVersionDeletedListener',
        ],
        'App\Events\V1\ApplianceDeletedEvent' => [
            'App\Listeners\V1\ApplianceDeletedListener',
        ],
        'App\Events\V1\AppliancePodAvailabilityDeletedEvent' => [
            'App\Listeners\V1\AppliancePodAvailabilityDeletedListener',
        ],
        'App\Events\V1\EncryptionEnabledOnSolutionEvent' => [
            'App\Listeners\V1\EncryptionEnabledOnSolutionListener',
        ],
        'App\Events\V1\ApplianceLaunchedEvent' => [
            'App\Listeners\V1\ApplianceLaunchedListener',
        ],
        'App\Events\V1\DatastoreCreatedEvent' => [
            'App\Listeners\V1\DatastoreCreatedListener',
        ],
        'App\Events\V1\DatastoreExpandEvent' => [
            'App\Listeners\V1\DatastoreExpandListener',
        ],
        'App\Events\V1\VolumeSetIopsUpdatedEvent' => [
            'App\Listeners\V1\VolumeSetIopsUpdatedListener',
        ],
        ///////////////////////////////////////////////////////////////////////////////////////////////
        // V2
        ///////////////////////////////////////////////////////////////////////////////////////////////

        // AvailabilityZone
        \App\Events\V2\AvailabilityZone\Creating::class => [
        ],

        // AvailabilityZoneCapacity
        \App\Events\V2\AvailabilityZoneCapacity\Saved::class => [
            \App\Listeners\V2\AvailabilityZoneCapacity\SendAlert::class,
        ],

        // Credential
        \App\Events\V2\Credential\Creating::class => [
        ],

        // Dhcp
        \App\Events\V2\Dhcp\Created::class => [
            \App\Listeners\V2\Nsx\Dhcp\Create::class,
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Dhcp\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Dhcp\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // FirewallPolicy
        \App\Events\V2\FirewallPolicy\Saving::class => [
        ],
        \App\Events\V2\FirewallPolicy\Saved::class => [
        ],
        \App\Events\V2\FirewallPolicy\Deleting::class => [
        ],
        \App\Events\V2\FirewallPolicy\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // FirewallRule
        \App\Events\V2\FirewallRule\Saving::class => [
        ],
        \App\Events\V2\FirewallRule\Saved::class => [
        ],
        \App\Events\V2\FirewallRule\Deleting::class => [
        ],
        \App\Events\V2\FirewallRule\Deleted::class => [
            \App\Listeners\V2\FirewallRule\Undeploy::class,
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // FirewallRulePort
        \App\Events\V2\FirewallRulePort\Saving::class => [
        ],
        \App\Events\V2\FirewallRulePort\Saved::class => [
        ],
        \App\Events\V2\FirewallRulePort\Deleting::class => [
        ],
        \App\Events\V2\FirewallRulePort\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // FloatingIp
        \App\Events\V2\FloatingIp\Created::class => [
            \App\Listeners\V2\FloatingIp\AllocateIp::class,
        ],
        \App\Events\V2\FloatingIp\Deleted::class => [
            \App\Listeners\V2\AvailabilityZoneCapacity\UpdateFloatingIpCapacity::class,
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // Instance
        \App\Events\V2\Instance\Creating::class => [
            \App\Listeners\V2\Instance\DefaultPlatform::class,
        ],
        \App\Events\V2\Instance\Created::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Instance\Deploy::class => [
            \App\Listeners\V2\Instance\Deploy::class,
        ],
        \App\Events\V2\Instance\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Instance\Updated::class => [
            \App\Listeners\V2\Instance\ComputeChange::class
        ],
        \App\Events\V2\Instance\Deleting::class => [
        ],
        \App\Events\V2\Instance\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // LoadBalancerCluster
        \App\Events\V2\LoadBalancerCluster\Creating::class => [
        ],

        // Network
        \App\Events\V2\Network\Creating::class => [
            \App\Listeners\V2\Network\DefaultSubnet::class,
        ],
        \App\Events\V2\Network\Created::class => [
            \App\Listeners\V2\Network\Deploy::class,
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Network\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Network\Saved::class => [
            \App\Listeners\V2\Network\Update::class,
        ],
        \App\Events\V2\Network\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // Nat
        \App\Events\V2\Nat\Created::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Nat\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Nat\Saved::class => [
            \App\Listeners\V2\Nat\Deploy::class,
        ],
        \App\Events\V2\Nat\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // Nic
        \App\Events\V2\Nic\Creating::class => [
        ],
        \App\Events\V2\Nic\Created::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Nic\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Nic\Deleting::class => [
        ],
        \App\Events\V2\Nic\Deleted::class => [
            \App\Listeners\V2\Nic\DeleteDhcpLease::class,       // TODO :- Needs moving to the Sync Nic delete
            \App\Listeners\V2\Nic\UnassignFloatingIp::class,    // TODO :- Needs moving to the Sync Nic delete
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // Region
        \App\Events\V2\Region\Creating::class => [
        ],

        // Router
        \App\Events\V2\Router\Creating::class => [
        ],
        \App\Events\V2\Router\Created::class => [
            \App\Listeners\V2\Router\Deploy::class,
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Router\Saved::class => [
            \App\Listeners\V2\Router\Update::class,
        ],
        \App\Events\V2\Router\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],
        \App\Events\V2\Router\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],

        // Volume
        \App\Events\V2\Volume\Saving::class => [
            \App\Listeners\V2\ResourceSync::class,
        ],
        \App\Events\V2\Volume\Saved::class => [
            \App\Listeners\V2\Volume\CapacityIncrease::class,
        ],
        \App\Events\V2\Volume\Deleting::class => [
        ],
        \App\Events\V2\Volume\Deleted::class => [
            \App\Listeners\V2\BillingMetric\End::class,
        ],

        // Vpc
        \App\Events\V2\Vpc\Creating::class => [
        ],
        \App\Events\V2\Vpc\Created::class => [
            \App\Listeners\V2\Vpc\Dhcp\Create::class,
        ],
        \App\Events\V2\Vpc\Deleted::class => [
            \App\Listeners\V2\Vpc\Dhcp\Delete::class,        // TODO :- Needs moving to the Sync Vpc delete
            \App\Listeners\V2\Vpc\Routers\Delete::class,     // TODO :- Needs moving to the Sync Vpc delete
            \App\Listeners\V2\Vpc\FloatingIps\Delete::class, // TODO :- Needs moving to the Sync Vpc delete
        ],

        // Vpn
        \App\Events\V2\Vpn\Creating::class => [
        ],

        // Sync
        \App\Events\V2\Sync\Updated::class => [
            \App\Listeners\V2\Volume\UpdateBilling::class,
            \App\Listeners\V2\Instance\UpdateRamBilling::class,
            \App\Listeners\V2\Instance\UpdateVcpuBilling::class,
            \App\Listeners\V2\Instance\UpdateLicenseBilling::class,
            \App\Listeners\V2\Instance\UpdateBackupBilling::class,
        ]
    ];
}
