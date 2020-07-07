<?php

/**
 * v2 Routes
 */

$middleware = [
    'auth',
    'paginator-limit:'.env('PAGINATION_LIMIT')
];

$baseRouteParameters = [
    'prefix' => 'v2',
    'namespace' => 'V2',
    'middleware' => $middleware
];

/** @var \Laravel\Lumen\Routing\Router $router */
$router->group($baseRouteParameters, function () use ($router) {

    /** Availability Zones */
    $router->group(['middleware' => 'is-administrator'], function () use ($router) {
        $router->get('availability-zones', 'AvailabilityZonesController@index');
        $router->get('availability-zones/{zoneId}', 'AvailabilityZonesController@show');
        $router->post('availability-zones', 'AvailabilityZonesController@create');
        $router->patch('availability-zones/{zoneId}', 'AvailabilityZonesController@update');
        $router->delete('availability-zones/{zoneId}', 'AvailabilityZonesController@destroy');
    });

});