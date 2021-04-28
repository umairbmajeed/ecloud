<?php

namespace Tests\unit\Jobs\Router;

use App\Jobs\Router\Deploy;
use App\Models\V2\Router;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class DeployTest extends TestCase
{
    use DatabaseMigrations;

    protected Router $router;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testPopulatesTier0PathAndSucceeds()
    {
        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('policy/api/v1/search/query?query=resource_type:GatewayQosProfile%20AND%20committed_bandwitdth:' . $this->routerThroughput()->committed_bandwidth)
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 1,
                    'results' => [
                        [
                            'path' => '/some/qos/path'
                        ]
                    ]
                ]));
            });

        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->router()->id])
            ->andThrow(
                new ClientException('Not Found', new Request('GET', 'test'), new Response(404))
            );

        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('/policy/api/v1/search/query?query=resource_type:Tier0%20AND%20tags.scope:' . config('defaults.tag.scope') . '%20AND%20tags.tag:az-default')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 1,
                    'results' => [
                        [
                            'path' => '/some/tier0/path'
                        ]
                    ]
                ]));
            });

        $this->nsxServiceMock()->expects('patch')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->router()->id, [
                'json' => [
                    'tier0_path' => '/some/tier0/path',
                    'tags' => [
                        [
                            'scope' => config('defaults.tag.scope'),
                            'tag' => $this->router()->vpc_id,
                        ],
                    ],
                    'route_advertisement_types' => [
                        'TIER1_IPSEC_LOCAL_ENDPOINT',
                        'TIER1_STATIC_ROUTES',
                        'TIER1_NAT'
                    ],
                    'qos_profile' => [
                        'egress_qos_profile_path' => '/some/qos/path',
                        'ingress_qos_profile_path' => '/some/qos/path'
                    ]
                ],
            ]])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        Event::fake([JobFailed::class]);

        dispatch(new Deploy($this->router()));

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testExistsDoesntPopulateTier0PathAndSucceeds()
    {
        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('policy/api/v1/search/query?query=resource_type:GatewayQosProfile%20AND%20committed_bandwitdth:' . $this->routerThroughput()->committed_bandwidth)
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 1,
                    'results' => [
                        [
                            'path' => '/some/qos/path'
                        ]
                    ]
                ]));
            });

        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->router()->id])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        $this->nsxServiceMock()->expects('patch')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->router()->id, [
                'json' => [
                    'tags' => [
                        [
                            'scope' => config('defaults.tag.scope'),
                            'tag' => $this->router()->vpc_id,
                        ],
                    ],
                    'route_advertisement_types' => [
                        'TIER1_IPSEC_LOCAL_ENDPOINT',
                        'TIER1_STATIC_ROUTES',
                        'TIER1_NAT'
                    ],
                    'qos_profile' => [
                        'egress_qos_profile_path' => '/some/qos/path',
                        'ingress_qos_profile_path' => '/some/qos/path'
                    ]
                ],
            ]])
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([]));
            });

        Event::fake([JobFailed::class]);

        dispatch(new Deploy($this->router()));

        Event::assertNotDispatched(JobFailed::class);
    }

    public function testRouterNoThroughputFails()
    {
        Model::withoutEvents(function() {
            $this->router = factory(Router::class)->create([
                'id' => 'rtr-test',
            ]);
        });

        Event::fake([JobFailed::class]);

        dispatch(new Deploy($this->router));

        Event::assertDispatched(JobFailed::class, function ($event) {
            return $event->exception->getMessage() == 'Failed determine router throughput settings for router rtr-test';
        });
    }

    public function testNoQoSProfileFoundFails()
    {
        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('policy/api/v1/search/query?query=resource_type:GatewayQosProfile%20AND%20committed_bandwitdth:' . $this->routerThroughput()->committed_bandwidth)
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 0,
                ]));
            });

        Event::fake([JobFailed::class]);

        dispatch(new Deploy($this->router()));

        Event::assertDispatched(JobFailed::class, function ($event) {
            return $event->exception->getMessage() == 'Failed to determine gateway QoS profile for router ' . $this->router()->id . ', with router_throughput_id ' . $this->routerThroughput()->id;
        });
    }

    public function testNoTier0FoundFails()
    {
        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('policy/api/v1/search/query?query=resource_type:GatewayQosProfile%20AND%20committed_bandwitdth:' . $this->routerThroughput()->committed_bandwidth)
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 1,
                    'results' => [
                        [
                            'path' => '/some/qos/path'
                        ]
                    ]
                ]));
            });

        $this->nsxServiceMock()->expects('get')
            ->withArgs(['policy/api/v1/infra/tier-1s/' . $this->router()->id])
            ->andThrow(
                new ClientException('Not Found', new Request('GET', 'test'), new Response(404))
            );

        $this->nsxServiceMock()->expects('get')
            ->withSomeOfArgs('/policy/api/v1/search/query?query=resource_type:Tier0%20AND%20tags.scope:' . config('defaults.tag.scope') . '%20AND%20tags.tag:az-default')
            ->andReturnUsing(function () {
                return new Response(200, [], json_encode([
                    'result_count' => 0,
                ]));
            });

        Event::fake([JobFailed::class]);

        dispatch(new Deploy($this->router()));

        Event::assertDispatched(JobFailed::class, function ($event) {
            return $event->exception->getMessage() == 'No tagged T0 could be found';
        });
    }
}
