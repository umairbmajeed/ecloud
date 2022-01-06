<?php

namespace App\Services\V2;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

final class KingpinService
{
    const INSTANCE_POWERSTATE_POWEREDON = 'poweredOn';
    const INSTANCE_POWERSTATE_POWEREDOFF = 'poweredOff';
    const INSTANCE_TOOLSRUNNINGSTATUS_RUNNING = 'guestToolsRunning';

    /** Endpoints; */
    public const GET_CONSOLE_SCREENSHOT = '/api/v2/vpc/%s/instance/%s/screenshot';
    public const POST_CONSOLE_SESSION = '/api/v2/vpc/%s/instance/%s/console/session';

    /**
     * @var Client
     */
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function __call($name, $arguments)
    {
        if (app()->environment() === 'testing') {
            Log::error('Called Kingpin without a mock!', [$name, $arguments]);
            dd([
                'Kingpin Method' => $name,
                'Kingpin Arguments' => $arguments,
            ]);
        }
        return call_user_func_array([$this->client, $name], $arguments);
    }
}
