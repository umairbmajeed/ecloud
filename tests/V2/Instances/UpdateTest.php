<?php

namespace Tests\V2\Instances;

use App\Models\V2\ApplianceVersionData;
use App\Models\V2\Task;
use App\Support\Sync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $task = app()->make(Task::class);
        $task->id = 't-test';
        app()->instance(Task::class, $task);
    }

    public function testFailedHostGroupCausesFail()
    {
        // Force failure
        Model::withoutEvents(function () {
            $model = new Task([
                'id' => 'sync-test',
                'failure_reason' => 'Unit Test Failure',
                'completed' => true,
                'name' => Sync::TASK_NAME_UPDATE,
            ]);
            $model->resource()->associate($this->hostGroup());
            $model->save();
        });

        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            [
                'host_group_id' => $this->hostGroup()->id,
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeJson(
            [
                'title' => 'Validation Error',
                'detail' => 'The specified host group id resource is currently in a failed state and cannot be used',
            ]
        )->assertResponseStatus(422);
    }

    public function testValidDataIsSuccessful()
    {
        Event::fake();
        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            [
                'name' => 'Changed',
                'backup_enabled' => true,
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeInDatabase(
            'instances',
            [
                'id' => $this->instance()->id,
                'name' => 'Changed'
            ],
            'ecloud'
        )
            ->assertResponseStatus(202);

        $this->instance()->refresh();
        $this->assertEquals('Changed', $this->instance()->name);
        $this->assertTrue($this->instance()->backup_enabled);
    }

    public function testAdminCanModifyLockedInstance()
    {
        Event::fake();

        // Lock the instance
        $this->instance()->locked = true;
        $this->instance()->save();

        $data = [
            'name' => 'Changed',
        ];
        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeInDatabase(
            'instances',
            [
                'id' => $this->instance()->id,
                'name' => 'Changed'
            ],
            'ecloud'
        )
            ->assertResponseStatus(202);

    }

    public function testScopedAdminCanNotModifyLockedInstance()
    {
        Event::fake();

        $this->instance()->locked = true;
        $this->instance()->save();
        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            [
                'name' => 'Testing Locked Instance',
            ],
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
                'X-Reseller-Id' => '1',
            ]
        )
            ->seeJson([
                'title' => 'Forbidden',
                'detail' => 'The specified instance is locked',
                'status' => 403,
            ])
            ->assertResponseStatus(403);
    }

    public function testLockedInstanceIsNotEditable()
    {
        Event::fake();

        // Lock the instance
        $this->instance()->locked = true;
        $this->instance()->save();
        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            [
                'name' => 'Testing Locked Instance',
            ],
            [
                'X-consumer-custom-id' => '1-1',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Forbidden',
                'detail' => 'The specified instance is locked',
                'status' => 403,
            ])
            ->assertResponseStatus(403);

        // Unlock the instance
        $this->instance()->locked = false;
        $this->instance()->saveQuietly();

        $data = [
            'name' => 'Changed',
        ];
        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            $data,
            [
                'X-consumer-custom-id' => '1-1',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )->seeInDatabase(
            'instances',
            [
                'id' => $this->instance()->id,
                'name' => 'Changed'
            ],
            'ecloud'
        )
            ->assertResponseStatus(202);
    }

    public function testApplianceSpecRamMax()
    {
        factory(ApplianceVersionData::class)->create([
            'key' => 'ukfast.spec.ram.max',
            'value' => 2048,
            'appliance_version_uuid' => $this->applianceVersion()->appliance_version_uuid,
        ]);

        $data = [
            'ram_capacity' => 3072,
        ];

        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified ram capacity is above the maximum of 2048',
                'status' => 422,
                'source' => 'ram_capacity'
            ])->assertResponseStatus(422);
    }

    public function testApplianceSpecVcpuMax()
    {
        factory(ApplianceVersionData::class)->create([
            'key' => 'ukfast.spec.cpu_cores.max',
            'value' => 5,
            'appliance_version_uuid' => $this->applianceVersion()->appliance_version_uuid,
        ]);

        $data = [
            'vcpu_cores' => 6,
        ];

        $this->patch(
            '/v2/instances/' . $this->instance()->id,
            $data,
            [
                'X-consumer-custom-id' => '0-0',
                'X-consumer-groups' => 'ecloud.write',
            ]
        )
            ->seeJson([
                'title' => 'Validation Error',
                'detail' => 'Specified vcpu cores is above the maximum of 5',
                'status' => 422,
                'source' => 'vcpu_cores'
            ])->assertResponseStatus(422);
    }
}
