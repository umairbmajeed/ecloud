<?php

namespace App\Http\Requests\V2\Instance;

use App\Models\V2\HostGroup;
use App\Rules\V2\ExistsForUser;
use App\Rules\V2\IsResourceAvailable;
use UKFast\FormRequests\FormRequest;

class HostGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'host_group_id' => [
                'required',
                'string',
                'exists:ecloud.host_groups,id,deleted_at,NULL',
                new ExistsForUser(HostGroup::class),
                new IsResourceAvailable(HostGroup::class),
            ]
        ];
    }
}
