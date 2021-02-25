<?php

namespace Tests\V1;

use App\Models\V1\Solution;
use App\Solution\CanModifyResource;
use App\Solution\Exceptions\InvalidSolutionStateException;
use App\Solution\Status;
use Laravel\Lumen\Testing\DatabaseMigrations;
use UKFast\Api\Auth\Consumer;

class CanModifyResourceTest extends TestCase
{
    use DatabaseMigrations;

    public function invalidStatuses()
    {
        return [
            [Status::CUSTOM],
            [Status::CANCELLED],
            [Status::UNKNOWN]
        ];
    }

    public function validStatuses()
    {
        return [
            [CanModifyResource::ALLOWED_STATUSES],
        ];
    }

    /**
     * @test
     * @dataProvider validStatuses
     */
    public function allows_modify_when_solution_completed($status)
    {
        $this->be((new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']))->setIsAdmin(false));

        $solution = (factory(Solution::class, 1)->create())->first();

        $solution->ucs_reseller_status = Status::COMPLETED;

        $check = new CanModifyResource($solution);

        $this->assertTrue($check->validate());
    }

    /**
     * @test
     * @dataProvider invalidStatuses
     */
    public function throws_exception_when_in_invalid_state($status)
    {
        $this->be((new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']))->setIsAdmin(false));

        $solution = (factory(Solution::class, 1)->create())->first();
        $solution->ucs_reseller_status = $status;

        $check = new CanModifyResource($solution);

        try {
            $check->validate();
        } catch (InvalidSolutionStateException $e) {
            $this->assertEquals($status, $e->getState());
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('Cannot modify resources whilst solution state is: ' . $status, $e->detail);
            return;
        }

        // If it reaches this point, then the exception wasn't thrown
        $this->expectException(InvalidSolutionStateException::class);
    }
}
