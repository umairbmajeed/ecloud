<?php

namespace Tests\V2\Vpn;

use App\Models\V2\Vpn;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class GetTest extends TestCase
{
    use DatabaseMigrations;

    protected $vpn;

    public function setUp(): void
    {
        parent::setUp();

        $this->vpn = factory(Vpn::class)->create([
            'router_id' => $this->router()->id,
        ]);
    }

    public function testGetCollection()
    {
        $this->get(
            '/v2/vpns',
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.read',
            ]
        )
            ->seeJson([
                'id' => $this->vpn->getKey(),
                'router_id' => $this->vpn->router_id,
                'availability_zone_id' => $this->vpn->availability_zone_id,
            ])
            ->assertResponseStatus(200);
    }

    public function testGetItemDetail()
    {
        $this->get(
            '/v2/vpns/' . $this->vpn->getKey(),
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.read',
            ]
        )
            ->seeJson([
                'id' => $this->vpn->getKey(),
                'router_id' => $this->vpn->router_id,
                'availability_zone_id' => $this->vpn->availability_zone_id,
            ])
            ->assertResponseStatus(200);
    }
}
