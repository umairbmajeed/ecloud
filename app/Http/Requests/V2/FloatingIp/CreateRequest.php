<?php

namespace App\Http\Requests\V2\FloatingIp;

use App\Models\V2\AvailabilityZone;
use App\Models\V2\Vpc;
use App\Rules\V2\ExistsForUser;
use App\Rules\V2\IpAddress\IsAvailable;
use App\Rules\V2\IpAddress\IsInSubnet;
use App\Rules\V2\IsResourceAvailable;
use UKFast\FormRequests\FormRequest;

/**
 * Class CreateFloatingIpRequest
 * @package App\Http\Requests\V2
 */
class CreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'vpc_id' => [
                'required',
                'string',
                'exists:ecloud.vpcs,id,deleted_at,NULL',
                new ExistsForUser(Vpc::class),
                new IsResourceAvailable(Vpc::class),
            ],
            'availability_zone_id' => [
                'required',
                'string',
                'exists:ecloud.availability_zones,id,deleted_at,NULL',
                new ExistsForUser(AvailabilityZone::class),
            ],
            'hostname' => [
                'sometimes',
                'ip',
                new IsInSubnet(app('request')->input('hostname')),
                'bail',
                new IsAvailable(app('request')->input('hostname')),
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array|string[]
     */
    public function messages()
    {
        return [
            'exists' => 'The specified :attribute was not found',
        ];
    }
}
