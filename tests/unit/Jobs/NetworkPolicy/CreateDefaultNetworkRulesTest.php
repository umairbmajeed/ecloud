<?php

namespace Tests\unit\Jobs\NetworkPolicy;

use App\Jobs\NetworkPolicy\CreateDefaultNetworkRules;
use App\Models\V2\NetworkRule;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateDefaultNetworkRulesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testSucceeds()
    {
        $this->networkPolicy();

        $this->assertEquals($this->networkPolicy()->networkRules()->count(), 0);

        Event::fake([JobFailed::class]);

        dispatch(new CreateDefaultNetworkRules($this->networkPolicy()));

        $this->assertEquals($this->networkPolicy()->networkRules()->count(), 2);

        $this->seeInDatabase('network_rules', [
            'name' => NetworkRule::TYPE_DHCP_INGRESS,
            'sequence' => 5001,
            'network_policy_id' => $this->networkPolicy()->id,
            'source' => '10.0.0.2',
            'destination' => 'ANY',
            'action' => 'ALLOW',
            'direction' => 'IN',
            'enabled' => true,
            'type' => NetworkRule::TYPE_DHCP_INGRESS
        ], 'ecloud');

        $this->seeInDatabase('network_rules', [
            'name' => NetworkRule::TYPE_DHCP_EGRESS,
            'sequence' => 5002,
            'network_policy_id' => $this->networkPolicy()->id,
            'source' => 'ANY',
            'destination' => 'ANY',
            'action' => 'ALLOW',
            'direction' => 'OUT',
            'enabled' => true,
            'type' => NetworkRule::TYPE_DHCP_EGRESS
        ], 'ecloud');

        Event::assertNotDispatched(JobFailed::class);
    }
}
