<?php

namespace App\Http\Requests\V2\BillingMetric;

use App\Models\V2\Instance;
use App\Models\V2\Router;
use App\Models\V2\Volume;
use App\Models\V2\Vpn;
use App\Rules\V2\ExistsForUser;
use UKFast\FormRequests\FormRequest;

class UpdateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'resource_id' => [
                'sometimes',
                'required',
                'string',
                new ExistsForUser([
                    Instance::class,
                    Router::class,
                    Volume::class,
                    Vpn::class,
                ])
            ],
            'key' => ['sometimes', 'required', 'string'],
            'value' => ['sometimes', 'required', 'string'],
            'cost' => ['sometimes', 'required', 'numeric'],
            'start' => ['sometimes', 'required', 'date'],
            'end' => ['sometimes', 'date'],
        ];
    }
}
