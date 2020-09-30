<?php

namespace Tests;

use App\Listeners\V2\DhcpCreate;
use App\Models\V1\Datastore;
use App\Models\V2\Appliance;
use App\Models\V2\ApplianceVersion;
use App\Models\V2\AvailabilityZone;
use App\Models\V2\Dhcp;
use App\Models\V2\FirewallRule;
use App\Models\V2\Instance;
use App\Models\V2\Router;
use App\Models\V2\Vpc;
use App\Models\V2\Network;

abstract class TestCase extends \Laravel\Lumen\Testing\TestCase
{

    protected $appliance;
    protected $appliance_version;

    public $validReadHeaders = [
        'X-consumer-custom-id' => '1-1',
        'X-consumer-groups' => 'ecloud.read',
    ];

    public $validWriteHeaders = [
        'X-consumer-custom-id' => '0-0',
        'X-consumer-groups' => 'ecloud.write',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Do not dispatch default ORM events on the following models, otherwise deployments will happen
        Datastore::flushEventListeners();
        Router::flushEventListeners();
        Dhcp::flushEventListeners();
        FirewallRule::flushEventListeners();
        Vpc::flushEventListeners();
        Network::flushEventListeners();
        $this->appliance = factory(Appliance::class)->create([
            'appliance_name' => 'Test Appliance',
        ])->refresh();
        $this->appliance_version = factory(ApplianceVersion::class)->create([
            'appliance_version_appliance_id' => $this->appliance->appliance_id,
        ])->refresh();
        Instance::flushEventListeners();
    }

    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }
}
