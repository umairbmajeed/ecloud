<?php

namespace Tests\V2\FirewallPolicy;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\FirewallPolicy;
use App\Models\V2\Region;
use App\Models\V2\Router;
use App\Models\V2\Vpc;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class GetTest extends TestCase
{
    use DatabaseMigrations;

    protected FirewallPolicy $policy;
    protected Region $region;
    protected Router $router;
    protected Vpc $vpc;

    public function setUp(): void
    {
        parent::setUp();

        $this->region = factory(Region::class)->create();
        factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->getKey(),
        ]);
        $this->vpc = factory(Vpc::class)->create([
            'region_id' => $this->region->getKey()
        ]);
        $this->router = factory(Router::class)->create([
            'vpc_id' => $this->vpc->getKey()
        ]);
        $this->policy = factory(FirewallPolicy::class)->create([
            'router_id' => $this->router->id,
        ])->first();
    }

    public function testGetCollection()
    {
        $this->get(
            '/v2/firewall-policies',
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups'    => 'ecloud.read',
            ]
        )
            ->seeJson([
                'id'        => $this->policy->getKey(),
                'name'      => $this->policy->name,
                'sequence'  => $this->policy->sequence,
                'router_id' => $this->router->getKey(),
            ])
            ->assertResponseStatus(200);
    }

    public function testGetResource()
    {
        $this->get(
            '/v2/firewall-policies/'.$this->policy->getKey(),
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups'    => 'ecloud.read',
            ]
        )
            ->seeJson([
                'id'        => $this->policy->getKey(),
                'name'      => $this->policy->name,
                'sequence'  => $this->policy->sequence,
                'router_id' => $this->router->getKey(),
            ])
            ->assertResponseStatus(200);
    }

}
