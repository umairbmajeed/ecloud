<?php

namespace Tests\V1\Appliances\ApplianceParameters;

use Laravel\Lumen\Testing\DatabaseMigrations;
use Tests\V1\ApplianceTestCase;

class PostTest extends ApplianceTestCase
{
    use DatabaseMigrations;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateApplianceVersionParameter()
    {
        $applianceVersion = $this->appliances[0]->getLatestVersion();

        // Assert record does not exist
        $this->missingFromDatabase(
            'appliance_script_parameters',
            [
                'appliance_script_parametsrs_key' => 'test'
            ],
            env('DB_ECLOUD_CONNECTION')
        );

        // Create the appliance record
        $this->json('POST', '/v1/appliance-parameters', [
            'version_id' => $applianceVersion->uuid,
            'name' => 'Test param',
            'type' => 'String',
            'key' => 'test',
            'description' => 'This is a test parameter',
            'required' => false

        ], $this->validWriteHeaders)->seeStatusCode(201);
    }

    public function testCreateApplianceVersionUnauthorised()
    {
        $applianceVersion = $this->appliances[0]->getLatestVersion();

        // Assert record does not exist
        $this->missingFromDatabase(
            'appliance_script_parameters',
            [
                'appliance_script_parametsrs_key' => 'test'
            ],
            env('DB_ECLOUD_CONNECTION')
        );

        // Create the appliance record
        $this->json('POST', '/v1/appliance-parameters', [
            'version_id' => $applianceVersion->uuid,
            'name' => 'Test param',
            'type' => 'String',
            'key' => 'test',
            'description' => 'This is a test parameter',
            'required' => false

        ], $this->validReadHeaders)->seeStatusCode(401);
    }
}
