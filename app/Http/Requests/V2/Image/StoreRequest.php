<?php

namespace App\Http\Requests\V2\Image;

use App\Models\V2\Image;
use UKFast\FormRequests\FormRequest;

class StoreRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255'
            ],
            'availability_zone_ids' => [
                'required',
                'array'
            ],
            'availability_zone_ids.*' => [
                'required',
                'string',
                'exists:ecloud.availability_zones,id,deleted_at,NULL',
            ],
            'logo_uri' => [
                'nullable',
                'max:255'
            ],
            'documentation_uri' => ['nullable'],
            'description' => ['nullable'],
            'script_template' => ['nullable'],
            'vm_template' => ['nullable'],
            'platform' => ['required'],
            'active' => [
                'required',
                'boolean'
            ],
            'public' => [
                'required',
                'boolean'
            ],
            'visibility' => [
                'required',
                'string',
                'in:' . Image::VISIBILITY_PUBLIC . ','. Image::VISIBILITY_PRIVATE
            ],
        ];
    }
}
