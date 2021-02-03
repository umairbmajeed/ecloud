<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\V2\LoadBalancerCluster;

$factory->define(LoadBalancerCluster::class, function () {
    return [
        'name' => 'Load Balancer Cluster 1',
        'nodes' => 3,
        'config_id' => '77898345-2a38-4a18-92c0-59a1f8681b65'
    ];
});

