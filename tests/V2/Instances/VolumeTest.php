<?php

namespace Tests\V2\Instances;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\Instance;
use App\Models\V2\Region;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class VolumeTest extends TestCase
{
    use DatabaseMigrations;

    protected $faker;

    protected $vpc;

    protected $instance;

    protected $volume;

    public function setUp(): void
    {
        parent::setUp();

        $region = factory(Region::class)->create();
        $availabilityZone = factory(AvailabilityZone::class)->create([
            'region_id' => $region->id,
        ]);
        $this->vpc = factory(Vpc::class)->create([
            'name' => 'Manchester VPC',
            'region_id' => $region->id,
        ]);
        $this->instance = factory(Instance::class)->create([
            'vpc_id' => $this->vpc->id,
            'availability_zone_id' => $availabilityZone->id,
        ]);
        $this->volume = factory(Volume::class)->create([
            'vpc_id' => $this->vpc->id,
        ]);
    }

    public function testGetVolumes()
    {
        $this->instance->volumes()->attach($this->volume);
        $this->get('/v2/instances/' . $this->instance->id . '/volumes', [
            'X-consumer-custom-id' => '1-0',
            'X-consumer-groups' => 'ecloud.read',
        ])->seeJson([
            'id' => $this->volume->id,
        ])->assertResponseStatus(200);
    }
}
