<?php

namespace Tests\V2\Instances;

use App\Models\V2\Appliance;
use App\Models\V2\ApplianceVersion;
use App\Models\V2\ApplianceVersionData;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Instance;
use App\Models\V2\Network;
use App\Models\V2\Region;
use App\Models\V2\Vpc;
use Faker\Factory as Faker;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use DatabaseMigrations;

    protected \Faker\Generator $faker;
    protected $availability_zone;
    protected $instance;
    protected $network;
    protected $region;
    protected $vpc;
    protected $appliance;
    protected $applianceVersion;

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
        ])->refresh();  // Hack needed since this is a V1 resource
        $this->applianceVersion = factory(ApplianceVersion::class)->create([
            'appliance_version_appliance_id' => $this->appliance->appliance_id,
        ])->refresh();  // Hack needed since this is a V1 resource
        $this->instance = factory(Instance::class)->create([
            'vpc_id' => $this->vpc->id,
            'appliance_version_id' => $this->applianceVersion->uuid,
            'availability_zone_id' => $this->availability_zone->getKey(),
        ]);
        $this->network = factory(Network::class)->create();
    }

    public function testValidDataSucceedsWithoutName()
    {
        // No name defined - defaults to ID
        $this->post('/v2/instances', [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
            'backup_enabled' => true,
        ], [
            'X-consumer-custom-id' => '0-0',
            'X-consumer-groups' => 'ecloud.write',
        ])->assertResponseStatus(201);

        $id = (json_decode($this->response->getContent()))->data->id;
        $this->seeJson([
            'id' => $id
        ])->seeInDatabase('instances', [
            'id' => $id,
            'name' => $id,
            'backup_enabled' => 1,
        ], 'ecloud');
    }

    public function testValidDataSucceedsWithName()
    {
        // Name defined
        $name = $this->faker->word();

        $this->post(
            '/v2/instances',
            [
                'name' => $name,
                'vpc_id' => $this->vpc->getKey(),
                'appliance_id' => $this->appliance->uuid,
                'network_id' => $this->network->id,
                'vcpu_cores' => 1,
                'ram_capacity' => 1024,
                'backup_enabled' => true,
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(201);

        $id = (json_decode($this->response->getContent()))->data->id;
        $this->seeInDatabase(
            'instances',
            [
                'id' => $id,
                'name' => $name,
                'backup_enabled' => 1,
            ],
            'ecloud'
        );
    }

    public function testAvailabilityZoneIdAutoPopulated()
    {
        $this->post(
            '/v2/instances',
            [
                'vpc_id' => $this->vpc->getKey(),
                'appliance_id' => $this->appliance->uuid,
                'network_id' => $this->network->id,
                'vcpu_cores' => 1,
                'ram_capacity' => 1024,
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(201);

        $id = (json_decode($this->response->getContent()))->data->id;
        $instance = Instance::findOrFail($id);
        $this->assertNotNull($instance->availability_zone_id);
    }

    public function testSettingApplianceVersionId()
    {
        // No name defined - defaults to ID
        $data = [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
        ];
        $this->post(
            '/v2/instances',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->assertResponseStatus(201);

        $id = json_decode($this->response->getContent())->data->id;
        $instance = Instance::findOrFail($id);
        // Check that the appliance id has been converted to the appliance version id
        $this->assertEquals($this->applianceVersion->uuid, $instance->appliance_version_id);
    }

    public function testApplianceSpecDefaultFallbacks()
    {
        $data = [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 11,
            'ram_capacity' => 512,
            'volume_capacity' => 30
        ];

        $this->post(
            '/v2/instances',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified vcpu cores is above the maximum of ' . config('instance.cpu_cores.max'),
                'status' => 422,
                'source' => 'ram_capacity'
            ])
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified ram capacity is below the minimum of ' . config('instance.ram_capacity.min'),
                'status' => 422,
                'source' => 'ram_capacity'
            ])
            ->assertResponseStatus(422);
    }

    public function testApplianceSpecRamMin()
    {
        factory(ApplianceVersionData::class)->create([
            'key' => 'ukfast.spec.ram.min',
            'value' => 2048,
            'appliance_version_uuid' => $this->applianceVersion->appliance_version_uuid,
        ]);

        $data = [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
            'volume_capacity' => 30
        ];

        $this->post(
            '/v2/instances',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified ram capacity is below the minimum of 2048',
                'status' => 422,
                'source' => 'ram_capacity'
            ])->assertResponseStatus(422);
    }

    public function testApplianceSpecVolumeMin()
    {
        factory(ApplianceVersionData::class)->create([
            'key' => 'ukfast.spec.volume.min',
            'value' => 50,
            'appliance_version_uuid' => $this->applianceVersion->appliance_version_uuid,
        ]);

        $data = [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
            'volume_capacity' => 30
        ];

        $this->post(
            '/v2/instances',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified volume capacity is below the minimum of 50',
                'status' => 422,
                'source' => 'volume_capacity'
            ])->assertResponseStatus(422);
    }

    public function testApplianceSpecVcpuMin()
    {
        factory(ApplianceVersionData::class)->create([
            'key' => 'ukfast.spec.cpu_cores.min',
            'value' => 2,
            'appliance_version_uuid' => $this->applianceVersion->appliance_version_uuid,
        ]);

        $data = [
            'vpc_id' => $this->vpc->getKey(),
            'appliance_id' => $this->appliance->uuid,
            'network_id' => $this->network->id,
            'vcpu_cores' => 1,
            'ram_capacity' => 1024,
            'volume_capacity' => 30
        ];

        $this->post(
            '/v2/instances',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified vcpu cores is below the minimum of 2',
                'status' => 422,
                'source' => 'vcpu_cores'
            ])->assertResponseStatus(422);
    }
}
