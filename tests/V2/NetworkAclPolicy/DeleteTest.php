<?php
namespace Tests\V2\NetworkAclPolicy;

use App\Models\V2\NetworkAclPolicy;
use App\Models\V2\Network;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use DatabaseMigrations;

    protected NetworkAclPolicy $aclPolicy;
    protected Network $network;

    public function setUp(): void
    {
        parent::setUp();
        $this->availabilityZone();
        $this->network = factory(Network::class)->create([
            'router_id' => $this->router()->id,
        ]);
        $this->aclPolicy = factory(NetworkAclPolicy::class)->create([
            'network_id' => $this->network->id,
            'vpc_id' => $this->vpc()->id,
        ]);
    }

    public function testDeleteResource()
    {
        $this->delete(
            '/v2/network-acl-policies/'.$this->aclPolicy->id,
            [],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->assertResponseStatus(204);
        $aclPolicy = NetworkAclPolicy::withTrashed()->findOrFail($this->aclPolicy->id);
        $this->assertNotNull($aclPolicy->deleted_at);
    }
}