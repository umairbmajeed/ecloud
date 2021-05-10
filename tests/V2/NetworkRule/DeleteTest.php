<?php
namespace Tests\V2\NetworkRule;

use App\Models\V2\NetworkPolicy;
use App\Models\V2\NetworkRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use UKFast\Api\Auth\Consumer;

class DeleteTest extends TestCase
{
    protected NetworkRule $networkRule;
    protected NetworkPolicy $networkPolicy;

    public function setUp(): void
    {
        parent::setUp();
        $this->be(new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']));

        Model::withoutEvents(function () {
            $this->networkRule = factory(NetworkRule::class)->make([
                'id' => 'nr-test-1',
                'name' => 'nr-test-1',
            ]);

            $this->networkRule->networkRulePorts()->create([
                'id' => 'nrp-test',
                'name' => 'nrp-test',
                'protocol' => 'TCP',
                'source' => '443',
                'destination' => '555',
            ]);

            $this->networkPolicy()->networkRules()->save($this->networkRule);
        });
    }

    public function testDeleteResource()
    {
        Event::fake([\App\Events\V2\Task\Created::class, \App\Events\V2\NetworkRule\Deleted::class]);

        $this->delete(
            '/v2/network-rules/' . $this->networkRule->id,
            [],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->assertResponseStatus(202);

        Event::assertDispatched(\App\Events\V2\Task\Created::class);
        Event::assertDispatched(\App\Events\V2\NetworkRule\Deleted::class);
    }
}