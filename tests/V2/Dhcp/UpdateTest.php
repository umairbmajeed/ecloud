<?php

namespace Tests\V2\Dhcp;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\Dhcp;
use App\Models\V2\Region;
use App\Models\V2\Vpc;
use Faker\Factory as Faker;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use DatabaseMigrations;

    protected $faker;
    protected $availabilityZone;
    protected $region;
    protected $vpc;
    protected $dhcp;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->region = factory(Region::class)->create([
            'name' => $this->faker->country(),
        ]);
        $this->availabilityZone = factory(AvailabilityZone::class)->create([
            'region_id' => $this->region->id,
        ]);
        $this->vpc = factory(Vpc::class)->create([
            'region_id' => $this->region->id,
        ]);
        $this->dhcp = factory(Dhcp::class)->create([
            'vpc_id' => $this->vpc->id,
            'availability_zone_id' => $this->availabilityZone->id
        ]);
    }

    public function testNoPermsIsDenied()
    {
        $this->patch(
            '/v2/dhcps/' . $this->dhcp->id,
            [
                'vpc_id' => $this->vpc->id,
            ],
            []
        )
            ->seeJson([
                'title' => 'Unauthorized',
                'detail' => 'Unauthorized',
                'status' => 401,
            ])
            ->assertResponseStatus(401);
    }

    public function testNullNameIsDenied()
    {
        $this->patch(
            '/v2/dhcps/' . $this->dhcp->id,
            [
                'vpc_id' => '',
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The vpc id field, when specified, cannot be null',
                'status' => 422,
                'source' => 'vpc_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testNotOwnedVpcIsFailed()
    {
        $vpc2 = factory(Vpc::class)->create([
            'reseller_id' => 3,
            'region_id' => $this->region->id
        ]);
        $this->patch(
            '/v2/dhcps/' . $this->dhcp->id,
            [
                'vpc_id' => $vpc2->id,
            ],
            [
                'X-consumer-custom-id' => '1-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The specified vpc id was not found',
                'status' => 422,
                'source' => 'vpc_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testValidDataIsSuccessful()
    {
        $data = [
            'vpc_id' => $this->vpc->id,
        ];
        $this->patch(
            '/v2/dhcps/' . $this->dhcp->id,
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->assertResponseStatus(200);

        $dhcp = Dhcp::findOrFail($this->dhcp->id);
        $this->assertEquals($data['vpc_id'], $this->dhcp->vpc_id);
    }
}
