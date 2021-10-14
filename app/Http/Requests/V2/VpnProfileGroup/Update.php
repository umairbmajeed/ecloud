<?php
namespace App\Http\Requests\V2\VpnProfileGroup;

use UKFast\FormRequests\FormRequest;

class Update extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|max:255',
            'availability_zone_id' => [
                'sometimes',
                'required',
                'string',
                'exists:ecloud.availability_zones,id,deleted_at,NULL',
            ],
            'ike_profile_id' => 'sometimes|required|string',
            'ipsec_profile_id' => 'sometimes|required|string'
        ];
    }
}
