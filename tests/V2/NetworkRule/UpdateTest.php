<?php
namespace Tests\V2\NetworkRule;

use App\Models\V2\NetworkPolicy;
use App\Models\V2\NetworkRule;
use GuzzleHttp\Psr7\Response;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use DatabaseMigrations;

    protected NetworkPolicy $networkPolicy;
    protected NetworkRule $networkRule;

    public function setUp(): void
    {
        parent::setUp();
        $this->network();

        $mockIds = ['np-test', 'np-alttest'];
        foreach ($mockIds as $mockId) {
            $this->nsxServiceMock()->shouldReceive('patch')
                ->withSomeOfArgs('/policy/api/v1/infra/domains/default/security-policies/' . $mockId)
                ->andReturnUsing(function () {
                    return new Response(200, [], '');
                });
            $this->nsxServiceMock()->shouldReceive('get')
                ->withSomeOfArgs('policy/api/v1/infra/realized-state/status?intent_path=/infra/domains/default/security-policies/' . $mockId)
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(
                        [
                            'publish_status' => 'REALIZED'
                        ]
                    ));
                });
            $this->nsxServiceMock()->shouldReceive('patch')
                ->withSomeOfArgs('/policy/api/v1/infra/domains/default/groups/' . $mockId)
                ->andReturnUsing(function () {
                    return new Response(200, [], '');
                });
            $this->nsxServiceMock()->shouldReceive('get')
                ->withArgs(['policy/api/v1/infra/realized-state/status?intent_path=/infra/domains/default/groups/' . $mockId])
                ->andReturnUsing(function () {
                    return new Response(200, [], json_encode(['publish_status' => 'REALIZED']));
                });
        }
        $this->networkPolicy = factory(NetworkPolicy::class)->create([
            'id' => 'np-test',
            'network_id' => $this->network()->id,
        ]);
        $this->networkRule = factory(NetworkRule::class)->create([
            'id' => 'nr-test',
            'network_policy_id' => $this->networkPolicy->id,
        ]);
    }

    public function testUpdateResource()
    {
        factory(NetworkPolicy::class)->create([
            'id' => 'np-alttest',
            'network_id' => $this->network()->id,
        ]);
        $this->patch(
            '/v2/network-rules/nr-test',
            [
                'network_policy_id' => 'np-alttest',
                'action' => 'REJECT',
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeInDatabase(
            'network_rules',
            [
                'id' => 'nr-test',
                'network_policy_id' => 'np-alttest',
                'action' => 'REJECT',
            ],
            'ecloud'
        )->assertResponseStatus(200);
    }
}