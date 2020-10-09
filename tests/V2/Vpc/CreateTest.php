<?php

namespace Tests\V2\Vpc;

use App\Events\V2\Dhcp\Created;
use App\Events\V2\DhcpCreated;
use App\Events\V2\VpcCreated;
use App\Models\V2\Dhcp;
use App\Models\V2\Region;
use App\Models\V2\Vpc;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class CreateTest extends TestCase
{

    use DatabaseMigrations;

    protected $faker;

    protected $region;

    public function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();

        $this->region = factory(Region::class)->create([
            'name' => 'Manchester',
        ]);
    }

    public function testNoPermsIsDenied()
    {
        $data = [
            'name' => 'Manchester DC',
        ];
        $this->post(
            '/v2/vpcs',
            $data,
            []
        )
            ->seeJson([
                'title' => 'Unauthorised',
                'detail' => 'Unauthorised',
                'status' => 401,
            ])
            ->assertResponseStatus(401);
    }

    public function testNullNameDefaultsToId()
    {
        $data = [
            'name' => '',
            'region_id' => $this->region->getKey(),
        ];
        $this->post(
            '/v2/vpcs',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
                'X-Reseller-Id' => 1,
            ]
        )->assertResponseStatus(201);

        $virtualPrivateCloudId = (json_decode($this->response->getContent()))->data->id;

        $this->seeJson([
            'id' => $virtualPrivateCloudId,
        ]);

        $vpc = Vpc::findOrFail($virtualPrivateCloudId);
        $this->assertEquals($virtualPrivateCloudId, $vpc->name);
    }

    public function testNullRegionIsFailed()
    {
        $data = [
            'name' => $this->faker->word(),
        ];
        $this->post(
            '/v2/vpcs',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
                'X-Reseller-Id' => 1,
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'The region id field is required',
                'status' => 422,
                'source' => 'region_id'
            ])
            ->assertResponseStatus(422);
    }

    public function testNotScopedFails()
    {
        $data = [
            'name' => $this->faker->word(),
            'reseller_id' => 1,
            'region_id' => $this->region->getKey()
        ];
        $this->post(
            '/v2/vpcs',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Bad Request',
                'detail' => 'Missing Reseller scope',
                'status' => 400,
            ])
            ->assertResponseStatus(400);
    }

    public function testValidDataSucceeds()
    {
        $data = [
            'name' => $this->faker->word(),
            'region_id' => $this->region->getKey(),
            'reseller_id' => 1
        ];
        $this->post(
            '/v2/vpcs',
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
                'X-Reseller-Id' => 1
            ]
        )
            ->seeInDatabase(
                'vpcs',
                $data,
                'ecloud'
            )
            ->assertResponseStatus(201);

        $virtualPrivateCloudId = (json_decode($this->response->getContent()))->data->id;
        $this->seeJson([
            'id' => $virtualPrivateCloudId,
        ]);
    }

    public function testCreateTriggersDhcpDispatch()
    {
        Event::fake();

        $vpc = factory(Vpc::class)->create([
            'id' => 'vpc-abc123'
        ]);

        Event::assertDispatched(\App\Events\V2\Vpc\Created::class, function ($event) use ($vpc) {
            return $event->model->id === $vpc->id;
        });

        $dhcp = factory(Dhcp::class)->create([
            'id' => 'dhcp-abc123',
            'vpc_id' => 'vpc-abc123'
        ]);

        Event::assertDispatched(Created::class, function ($event) use ($dhcp) {
            return $event->model->id === $dhcp->id;
        });
    }
}
