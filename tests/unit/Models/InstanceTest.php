<?php

namespace Tests\unit\Models;

use Illuminate\Support\Facades\Event;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\TestCase;

class InstanceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testDeleteFiresExpectedEvents()
    {
        Event::fake();

        $this->instance()->delete();

        Event::assertDispatched(\App\Events\V2\Instance\Deleted::class, function ($event)  {
            return $event->model->id === $this->instance()->id;
        });
    }
}
