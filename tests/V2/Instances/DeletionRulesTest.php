<?php

namespace Tests\V2\Instances;

use App\Models\V2\Appliance;
use App\Models\V2\ApplianceVersion;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Instance;
use App\Models\V2\Network;
use App\Models\V2\Nic;
use App\Models\V2\Region;
use App\Models\V2\Vpc;
use App\Services\V2\KingpinService;
use Faker\Factory as Faker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class DeletionRulesTest extends TestCase
{
    use DatabaseMigrations;

    protected \Faker\Generator $faker;

    protected Appliance $appliance;
    protected ApplianceVersion $appliance_version;
    protected AvailabilityZone $availability_zone;
    protected Instance $instance;
    protected Network $network;
    protected Nic $nics;
    protected Region $region;
    protected Vpc $vpc;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->region = factory(Region::class)->create();
        $this->availability_zone = factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->getKey()
        ]);

        $this->vpc = factory(Vpc::class)->create([
            'region_id' => $this->region->getKey()
        ]);
        $this->appliance = factory(Appliance::class)->create([
            'appliance_name' => 'Test Appliance',
        ])->refresh();
        $this->appliance_version = factory(ApplianceVersion::class)->create([
            'appliance_version_appliance_id' => $this->appliance->appliance_id,
        ])->refresh();
        $this->network = factory(Network::class)->create([
            'name' => 'Manchester Network',
        ]);
        $this->instance = factory(Instance::class)->create([
            'vpc_id' => $this->vpc->getKey(),
            'name' => 'DeleteTest Default',
            'appliance_version_id' => $this->appliance_version->uuid,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
        ]);
        $this->nics = factory(Nic::class)->create([
            'mac_address' => $this->faker->macAddress,
            'instance_id' => $this->instance->getKey(),
            'network_id' => $this->network->getKey(),
            'ip_address' => $this->faker->ipv4,
        ]);

        $mockKingpinService = \Mockery::mock(new KingpinService(new Client()))->makePartial();
        $mockKingpinService->shouldReceive('delete')
            ->withArgs(['/api/v2/vpc/' . $this->instance->vpc->getKey() . '/instance/' . $this->instance->getKey() . '/power'])
            ->andReturn(
                new Response(200)
            );
        $mockKingpinService->shouldReceive('delete')
            ->withArgs(['/api/v2/vpc/' . $this->instance->vpc->getKey() . '/instance/' . $this->instance->getKey()])
            ->andReturn(
                new Response(200)
            );

        app()->bind(KingpinService::class, function () use ($mockKingpinService) {
            return $mockKingpinService;
        });
    }

    public function testFailedDeletion()
    {
        $this->delete(
            '/v2/instances/' . $this->instance->getKey(),
            [],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeJson([
            'detail' => 'Active resources exist for this item',
        ])->assertResponseStatus(412);
        $instance = Instance::withTrashed()->findOrFail($this->instance->getKey());
        $this->assertNull($instance->deleted_at);
    }
}
