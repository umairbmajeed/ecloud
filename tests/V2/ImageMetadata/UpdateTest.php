<?php
namespace Tests\V2\ImageMetadata;

use Tests\TestCase;
use UKFast\Api\Auth\Consumer;

class UpdateTest extends TestCase
{
    public function testUpdateAdminSucceeds()
    {
        $this->be((new Consumer(0, [config('app.name') . '.read', config('app.name') . '.write']))->setIsAdmin(true));

        $this->patch(
            '/v2/image-metadata/' . $this->imageMetadata()->id,
            [
                'key' => 'KEY.UPDATED',
                'value' => 'VALUE.UPDATED',
            ])->seeInDatabase(
            'image_metadata',
            [
                'key' => 'KEY.UPDATED',
                'value' => 'VALUE.UPDATED',
            ],
            'ecloud'
        )->assertResponseStatus(200);
    }

    public function testUpdateNotAdminFails()
    {
        $this->be(new Consumer(1, [config('app.name') . '.read', config('app.name') . '.write']));
        $this->patch('/v2/image-metadata/' . $this->imageMetadata()->id, [])->assertResponseStatus(401);
    }
}