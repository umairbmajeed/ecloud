<?php
namespace Tests\V2\VpnSession;

use App\Models\V2\FloatingIp;
use App\Models\V2\VpnEndpoint;
use App\Models\V2\VpnService;
use App\Models\V2\VpnSession;
use Tests\TestCase;
use UKFast\Api\Auth\Consumer;

class CreateTest extends TestCase
{
    protected VpnService $vpnService;
    protected VpnEndpoint $vpnEndpoint;
    protected VpnSession $vpnSession;
    protected FloatingIp $floatingIp;

    public function setUp(): void
    {
        parent::setUp();

        $this->be(new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']));
        $this->floatingIp = FloatingIp::withoutEvents(function () {
            return factory(FloatingIp::class)->create([
                'id' => 'fip-abc123xyz',
            ]);
        });
        $this->vpnService = factory(VpnService::class)->create([
            'router_id' => $this->router()->id,
        ]);
        $this->vpnEndpoint = factory(VpnEndpoint::class)->create([
            'vpn_service_id' => $this->vpnService->id,
            'fip_id' => $this->floatingIp->id,
        ]);
        $this->vpnSession = factory(VpnSession::class)->create(
            [
                'name' => '',
                'vpn_service_id' => $this->vpnService->id,
                'vpn_endpoint_id' => $this->vpnEndpoint->id,
                'remote_ip' => '211.12.13.1',
                'remote_networks' => '127.1.1.1/32',
                'local_networks' => '127.1.1.1/32,127.1.10.1/24',
            ]
        );
    }

    public function testGetCollection()
    {
        $this->get('/v2/vpn-sessions')
            ->seeJson(
                [
                    'id' => $this->vpnSession->id,
                    'vpn_service_id' => $this->vpnService->id,
                    'vpn_endpoint_id' => $this->vpnEndpoint->id,
                ]
            )->assertResponseStatus(200);
    }

    public function testGetResource()
    {
        $this->get('/v2/vpn-sessions/' . $this->vpnSession->id)
            ->seeJson(
                [
                    'id' => $this->vpnSession->id,
                    'vpn_service_id' => $this->vpnService->id,
                    'vpn_endpoint_id' => $this->vpnEndpoint->id,
                ]
            )->assertResponseStatus(200);
    }
}