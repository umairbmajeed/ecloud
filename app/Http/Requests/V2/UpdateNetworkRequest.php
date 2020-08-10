<?php

namespace App\Http\Requests\V2;

use UKFast\FormRequests\FormRequest;

/**
 * Class UpdateNetworksRequest
 * @package App\Http\Requests\V2
 */
class UpdateNetworkRequest extends FormRequest
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
            'name'    => 'sometimes|required|string',
            'router_id'    => 'sometimes|required|string|exists:ecloud.routers,id,deleted_at,NULL',
            'availability_zone_id'    => 'sometimes|required|string|exists:ecloud.availability_zones,id,deleted_at,NULL',
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
            'name.required' => 'The :attribute field, when specified, cannot be null',
            'router_id.required' => 'The :attribute field, when specified, cannot be null',
            'router_id.exists' => 'The specified :attribute was not found',
            'availability_zone_id.required' => 'The :attribute field, when specified, cannot be null',
            'availability_zone_id.exists' => 'The specified :attribute was not found',
        ];
    }
}
