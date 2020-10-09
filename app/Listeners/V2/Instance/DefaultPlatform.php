<?php

namespace App\Listeners\V2\Instance;

use App\Events\V2\Instance\Created;
use App\Models\V2\Instance;
use Exception;
use Illuminate\Support\Facades\Log;

class DefaultPlatform
{
    public function handle(Created $event)
    {
        /** @var Instance $model */
        $model = $event->model;

        Log::info('Setting default platform on instance ' . $model->id);

        if (!empty($model->platform)) {
            Log::info('Platform already set to "' . $model->platform . '" on instance ' . $model->id);
            return;
        }

        if (!$model->applianceVersion) {
            Log::error('Failed to find appliance version for instance ' . $model->id);
            return;
        }

        try {
            $model->platform = $model->applianceVersion->serverLicense()->category;
            $model->save();
        } catch (Exception $exception) {
            Log::error('Failed to determine default platform from appliance version', [$exception]);
            throw $exception;
        }

        Log::info('Default platform on instance ' . $model->id . ' set to ' . $model->platform);
    }
}
