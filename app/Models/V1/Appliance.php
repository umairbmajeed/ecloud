<?php

namespace App\Models\V1;

use App\Traits\V1\ColumnPrefixHelper;
use App\Traits\V1\UUIDHelper;

use Illuminate\Database\Eloquent\Model;

use UKFast\Api\Resource\Property\BooleanProperty;
use UKFast\Api\Resource\Property\StringProperty;
use UKFast\Api\Resource\Property\IdProperty;

use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;
use UKFast\DB\Ditto\Filter;

class Appliance extends Model implements Filterable, Sortable
{
    // Table columns have table name prefixes
    use ColumnPrefixHelper;

    // Table uses UUID's
    use UUIDHelper;

    protected $connection = 'ecloud';

    protected $table = 'appliance';

    // Use UUID as primary key
    protected $primaryKey = 'appliance_uuid';
    // Don't increment the primary key for UUID's
    public $incrementing = false;

    // Automatically manage our timestamps
    public $timestamps = true;

    const CREATED_AT = 'appliance_created_at';

    const UPDATED_AT = 'appliance_updated_at';

    // Validation Rules
    public static $rules = [
        'name' => ['required',  'max:255'],
        'logo_uri' => ['nullable', 'max:255'],
        'description' => ['nullable'],
        'documentation_uri' => ['nullable'],
        'publisher' => ['nullable', 'max:255'],
        'active' => ['nullable', 'boolean']
    ];


    /**
     * Ditto configuration
     * ----------------------
     */

    /**
     * Ditto maps raw database names to friendly names.
     * @return array
     */
    public function databaseNames()
    {
        return [
            'id' => 'appliance_uuid', //UUID, not internal id
            'name' => 'appliance_name',
            'logo_uri' => 'appliance_logo_uri',
            'description' => 'appliance_description',
            'documentation_uri' => 'appliance_documentation_uri',
            'publisher' => 'appliance_publisher',
            'active' => 'appliance_active' // Yes / No
        ];
    }

    /**
     * Ditto filtering configuration
     * @param FilterFactory $factory
     * @return array
     */
    public function filterableColumns(FilterFactory $factory)
    {
        return [
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('description', Filter::$stringDefaults),
            $factory->create('publisher', Filter::$stringDefaults),
            $factory->create('active', Filter::$stringDefaults)
        ];
    }


    /**
     * Ditto sorting configuration
     * @param SortFactory $factory
     * @return array
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory)
    {
        return [
            $factory->create('name'),
            $factory->create('publisher'),
            $factory->create('active')
        ];
    }

    /**
     * Ditto sorting
     * @param SortFactory $sortFactory
     * @return array
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $sortFactory)
    {
        return [
            $sortFactory->create('name', 'asc'),
        ];
    }


    /**
     * Ditto Selectable persistent Properties
     * @return array
     */
    public function persistentProperties()
    {
        return ['id'];
    }


    /**
     * The attributes included in the model's JSON form.
     * Admin scope / everything
     *
     * @var array
     */
    protected $visible = [
        'appliance_uuid',
        'appliance_name',
        'appliance_logo_uri',
        'appliance_description',
        'appliance_documentation_uri',
        'appliance_publisher',
        'appliance_active'
    ];

    /**
     * Restrict visibility for non-admin
     */
    const VISIBLE_SCOPE_RESELLER = [
        'appliance_uuid',
        'appliance_name',
        'appliance_logo_uri',
        'appliance_description',
        'appliance_documentation_uri',
        'appliance_publisher'
    ];

    /**
     * Resource package
     * Map request property to database field
     *
     * @return array
     * @throws \UKFast\Api\Resource\Exceptions\InvalidPropertyException
     */
    public function properties()
    {
        return [
            IdProperty::create('appliance_uuid', 'id', null, 'uuid'),
            StringProperty::create('appliance_name', 'name'),
            StringProperty::create('appliance_logo_uri', 'logo_uri'),
            StringProperty::create('appliance_description', 'description'),
            StringProperty::create('appliance_documentation_uri', 'documentation_uri'),
            StringProperty::create('appliance_publisher', 'publisher'),
            BooleanProperty::create('appliance_active', 'active', null, 'Yes', 'No')
        ];
    }
}
