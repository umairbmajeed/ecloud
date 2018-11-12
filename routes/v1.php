<?php

/**
 * v1 Routes
 */

$middleware = [
    'auth',
    'paginator-limit:'.env('PAGINATION_LIMIT')
];

$baseRouteParameters = [
    'prefix' => 'v1',
    'middleware' => $middleware
];


// VM's
$virtualMachineRouteParameters = $baseRouteParameters;
$virtualMachineRouteParameters['namespace'] = 'V1';
$router->group($virtualMachineRouteParameters, function () use ($router) {
    // Return a VM Collection
    $router->get('vms', 'VirtualMachineController@index');

    //Return a VM Resource
    $router->get('vms/{vm_id}', 'VirtualMachineController@show');

    //Power the VM On
    $router->get('vms/{vm_id}/power-on', 'VirtualMachineController@powerOn');

    //Power the VM Off
    $router->get('vms/{vm_id}/power-off', 'VirtualMachineController@powerOff');

    //Power-cycle the VM
    $router->get('vms/{vm_id}/power-cycle', 'VirtualMachineController@powerCycle');
});


// Hybrid/Private Solution's
$solutionRouteParameters = array_merge($baseRouteParameters, array(
    'namespace' => 'V1',
    'prefix' => 'v1',
));
$router->group($solutionRouteParameters, function () use ($router) {
    // solutions
    $router->get('solutions', 'SolutionController@index');
    $router->get('solutions/{solution_id}', 'SolutionController@show');

    // solution vlan's
    $router->get('solutions/{solution_id}/vlans', 'VlanController@getSolutionVlans');
});
