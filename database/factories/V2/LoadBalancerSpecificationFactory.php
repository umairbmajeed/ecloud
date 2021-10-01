<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\V2\LoadBalancerSpecification;

$factory->define(LoadBalancerSpecification::class, function () {

        return [
            'name' => 'small',
            'description' => 'Description Test',
            'node_count' => 1,
            'cpu' => 1,
            'ram' => 2,
            'hdd' => 20,
            'iops' => 300,
            'image_id' => 'img-aaaaaaaa',
        ];
});