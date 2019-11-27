<?php

namespace Tests\V1\Appliance\Version;

use App\Models\V1\Appliance;
use App\Models\V1\ApplianceVersion;
use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Http\Response;

class DataTest extends TestCase
{
    use DatabaseTransactions, DatabaseMigrations;

    const TEST_DATA = [
        'key' => 'test-key',
        'value' => 'test-value',
    ];

    /**
     * @var ApplianceVersion
     */
    protected $applianceVersion;

    /**
     * Return the URI for the appliance version data endpoint or an invalid one
     * @param bool $valid
     * @param string $invalidValue
     * @return string
     */
    protected function getApplianceVersionDataUri(bool $valid = true, string $invalidValue = 'x')
    {
        $uuid = $valid ? $this->applianceVersion->appliance_version_uuid : $invalidValue;
        return '/v1/appliance-versions/' . $uuid . '/data';
    }

    protected function setUp() : void
    {
        parent::setUp();

        $this->applianceVersion = factory(ApplianceVersion::class)->create([
            'appliance_uuid' => function () {
                return factory(Appliance::class)->create()->appliance_uuid;
            },
            'appliance_version_version' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->applianceVersion = null;

        parent::tearDown();
    }

    public function valueDataProvider()
    {
        return [
            'valid_value_returns_OK' => [
                'data' => self::TEST_DATA,
                'responseCode' => Response::HTTP_OK,
                'databaseCheckMethod' => 'seeInDatabase',
            ],
            'invalid_value_returns_BAD_REQUEST' => [
                'data' => [
                    'key' => 'test-key',
                    'value' => '',
                ],
                'responseCode' => Response::HTTP_BAD_REQUEST,
                'databaseCheckMethod' => 'notSeeInDatabase',
            ],
        ];
    }

    /**
     * @dataProvider valueDataProvider
     * @param array $data
     * @param int $responseCode
     * @param string $databaseCheckMethod
     */
    public function testValue(array $data, int $responseCode, string $databaseCheckMethod)
    {
        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            $data,
            $this->validWriteHeaders
        );
        $response->seeStatusCode($responseCode);

        $this->$databaseCheckMethod(
            'appliance_version_data',
            $data + [
                'appliance_version_uuid' => $this->applianceVersion->appliance_version_uuid,
            ],
            'ecloud'
        );
    }

    public function applianceVersionUuidDataProvider()
    {
        return [
            'valid_appliance_version_uuid_returns_OK' => [
                'responseCode' => Response::HTTP_OK,
                'useValidUuid' => true,
            ],
            'invalid_appliance_version_uuid_returns_NOT_FOUND' => [
                'responseCode' => Response::HTTP_NOT_FOUND,
                'useValidUuid' => false,
            ],
        ];
    }

    /**
     * @dataProvider applianceVersionUuidDataProvider
     * @param int $responseCode
     * @param bool $useValidUuid
     */
    public function testApplianceVersionUuid(int $responseCode, bool $useValidUuid)
    {
        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri($useValidUuid),
            self::TEST_DATA,
            $this->validWriteHeaders
        );
        $response->seeStatusCode($responseCode);
    }

    public function applianceStateDataProvider()
    {
        $adminHeaders = [
            'X-consumer-custom-id' => '0-0',
            'X-consumer-groups' => 'ecloud.write',
        ];
        $nonAdminHeaders = [
            'X-consumer-custom-id' => '1-0',
            'X-consumer-groups' => 'ecloud.write',
        ];

        return [
            'appliance_active_and_public_returns_OK' => [
                'active' => 'Yes',
                'is_public' => 'Yes',
                'responseCode' => Response::HTTP_OK,
                'headersToUse' => $nonAdminHeaders,
            ],
            'appliance_not_active_returns_NOT_FOUND' => [
                'active' => 'No',
                'is_public' => 'Yes',
                'responseCode' => Response::HTTP_NOT_FOUND,
                'headersToUse' => $nonAdminHeaders,
            ],
            'appliance_not_public_returns_NOT_FOUND' => [
                'active' => 'Yes',
                'is_public' => 'No',
                'responseCode' => Response::HTTP_NOT_FOUND,
                'headersToUse' => $nonAdminHeaders,
            ],
            'appliance_active_and_public_as_admin_returns_OK' => [
                'active' => 'Yes',
                'is_public' => 'Yes',
                'responseCode' => Response::HTTP_OK,
                'headersToUse' => $adminHeaders,
            ],
            'appliance_not_active_as_admin_returns_NOT_FOUND' => [
                'active' => 'No',
                'is_public' => 'Yes',
                'responseCode' => Response::HTTP_NOT_FOUND,
                'headersToUse' => $adminHeaders,
            ],
            'appliance_not_public_as_admin_returns_NOT_FOUND' => [
                'active' => 'Yes',
                'is_public' => 'No',
                'responseCode' => Response::HTTP_OK,    // Admin always sees appliance
                'headersToUse' => $adminHeaders,
            ],
        ];
    }

    /**
     * @dataProvider applianceStateDataProvider
     * @param $active
     * @param $isPublic
     * @param $responseCode
     * @param $headersToUse
     */
    public function testApplianceState($active, $isPublic, $responseCode, $headersToUse)
    {
        $appliance = $this->applianceVersion->appliance;
        $appliance->active = $active;
        $appliance->is_public = $isPublic;
        $appliance->save();

        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            self::TEST_DATA,
            $headersToUse
        );
        $response->seeStatusCode($responseCode);
    }

    public function applianceVersionStateDataProvider()
    {
        return [
            'appliance_version_active_returns_OK' => [
                'active' => 'Yes',
                'responseCode' => Response::HTTP_OK,
            ],
            'appliance_version_not_active_returns_NOT_FOUND' => [
                'active' => 'No',
                'responseCode' => Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * @dataProvider applianceVersionStateDataProvider
     * @param $active
     * @param $responseCode
     */
    public function testApplianceVersionState($active, $responseCode)
    {
        $this->applianceVersion->active = $active;
        $this->applianceVersion->save();

        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            self::TEST_DATA,
            $this->validWriteHeaders
        );
        $response->seeStatusCode($responseCode);
    }

    public function testDuplicateKey()
    {
        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            self::TEST_DATA,
            $this->validWriteHeaders
        );
        $response->seeStatusCode(Response::HTTP_OK);

        $response = $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            self::TEST_DATA,
            $this->validWriteHeaders
        );
        $response->seeStatusCode(Response::HTTP_CONFLICT);
    }

    public function testDeleteExistingKey()
    {
        $this->json(
            'POST',
            $this->getApplianceVersionDataUri(),
            self::TEST_DATA,
            $this->validWriteHeaders
        );

        $response = $this->json(
            'DELETE',
            $this->getApplianceVersionDataUri() . '/test-key',
            [],
            $this->validWriteHeaders
        );
        $response->seeStatusCode(Response::HTTP_OK);

        $this->notSeeInDatabase(
            'appliance_version_data',
            self::TEST_DATA + [
                'appliance_version_uuid' => $this->applianceVersion->appliance_version_uuid,
                'deleted_at' => null,
            ],
            'ecloud'
        );
    }

    public function testDeleteNonExistentKey()
    {
        $response = $this->json(
            'DELETE',
            $this->getApplianceVersionDataUri() . '/test-key',
            [],
            $this->validWriteHeaders
        );
        $response->seeStatusCode(Response::HTTP_NOT_FOUND);
    }
}
