<?php

namespace App\Models\V2;

use App\Traits\V2\CustomKey;
use App\Traits\V2\DefaultName;
use App\Traits\V2\DeletionRules;
use App\Traits\V2\Syncable;
use App\Traits\V2\Taskable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use UKFast\Api\Auth\Consumer;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

/**
 * Class Image
 * @package App\Models\V2
 */
class Image extends Model implements Filterable, Sortable
{
    use CustomKey, SoftDeletes, DeletionRules, DefaultName, Syncable, Taskable;

    public string $keyPrefix = 'img';

    protected $casts = [
        'active' => 'boolean',
        'public' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        $this->incrementing = false;
        $this->keyType = 'string';
        $this->connection = 'ecloud';

        $this->fillable([
            'id',
            'name',
            'reseller_id',
            'logo_uri',
            'documentation_uri',
            'description',
            'script_template',
            'vm_template',
            'platform',
            'active',
            'public',
            'publisher'
        ]);
        parent::__construct($attributes);
    }

    public function vpc()
    {
        return $this->belongsTo(Vpc::class);
    }

    /**
     * Pivot table image_availability_zone
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function availabilityZones()
    {
        return $this->belongsToMany(AvailabilityZone::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function imageParameters()
    {
        return $this->hasMany(ImageParameter::class);
    }

    public function imageMetadata()
    {
        return $this->hasMany(ImageMetadata::class);
    }

    /**
     * DEPRECATED METHODS
     */

    /**
     * @return mixed
     * @deprecated
     */
    public function getNameAttribute()
    {
        return $this->applianceVersion->appliance->name;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getScriptTemplateAttribute()
    {
        return $this->applianceVersion->script_template;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getVMTemplateNameAttribute()
    {
        return $this->applianceVersion->appliance_version_vm_template;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getLogoURIAttribute()
    {
        return $this->applianceVersion->appliance->logo_uri;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getDocumentationURIAttribute()
    {
        return $this->applianceVersion->appliance->documentation_uri;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getDescriptionAttribute()
    {
        return $this->applianceVersion->appliance->description;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getActiveAttribute()
    {
        return $this->applianceVersion->appliance->active == "Yes";
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getIsPublicAttribute()
    {
        return $this->applianceVersion->appliance->is_public == "Yes";
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getPlatformAttribute()
    {
        return $this->applianceVersion->serverLicense()->category;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function getLicenseIDAttribute()
    {
        return $this->applianceVersion->serverLicense()->id;
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function parameters()
    {
        return $this->applianceVersion->applianceScriptParameters();
    }

    /**
     * @return mixed
     * @deprecated
     */
    public function metadata()
    {
        return $this->applianceVersion->applianceVersionData();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @deprecated
     */
    public function applianceVersion()
    {
        return $this->belongsTo(
            ApplianceVersion::class,
            'appliance_version_id',
            'appliance_version_uuid'
        );
    }

    /**
     * @param $query
     * @param $user
     * @return mixed
     * @deprecated
     */
    public function scopeForUser($query, Consumer $user)
    {
        if (!$user->isAdmin()) {
            return $query->whereHas('applianceVersion.appliance', function ($query) use ($user) {
                $query->where('appliance_is_public', 'Yes')
                    ->where('appliance_active', 'Yes');
            });
        }

        return $query;
    }

    /**
     * END DEPRECATED METHODS
     */


//    /**
//     * @param $query
//     * @param $user
//     * @return mixed
//     */
//    public function scopeForUser($query, Consumer $user)
//    {
//        if (!$user->isScoped()) {
//            return $query;
//        }
//
//        return $query->where(function ($query) use ($user) {
//            $query->where(function ($query) {
//                $query->where('public', true)->where('active', true);
//            })
//            ->orWhere(function ($query) use ($user) {
//                $query->where('reseller_id', $user->resellerId());
//            });
//        });
//    }

    public function isOwner(): bool
    {
        return $this->reseller_id == Auth::user()->resellerId();
    }

    /**
     * @param FilterFactory $factory
     * @return array|Filter[]
     */
    public function filterableColumns(FilterFactory $factory): array
    {
        return [
            $factory->create('id', Filter::$stringDefaults),
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('reseller_id', Filter::$stringDefaults),
            $factory->create('logo_uri', Filter::$stringDefaults),
            $factory->create('documentation_uri', Filter::$stringDefaults),
            $factory->create('description', Filter::$stringDefaults),
            $factory->create('script_template', Filter::$stringDefaults),
            $factory->create('vm_template', Filter::$stringDefaults),
            $factory->create('platform', Filter::$enumDefaults),
            $factory->create('active', Filter::$enumDefaults),
            $factory->create('public', Filter::$enumDefaults),
            $factory->create('publisher', Filter::$stringDefaults),
            $factory->create('created_at', Filter::$dateDefaults),
            $factory->create('updated_at', Filter::$dateDefaults),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort[]
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory): array
    {
        return [
            $factory->create('id'),
            $factory->create('name'),
            $factory->create('reseller_id'),
            $factory->create('logo_uri'),
            $factory->create('documentation_uri'),
            $factory->create('description'),
            $factory->create('script_template'),
            $factory->create('vm_template'),
            $factory->create('platform'),
            $factory->create('active'),
            $factory->create('publisher'),
            $factory->create('created_at'),
            $factory->create('updated_at'),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort|\UKFast\DB\Ditto\Sort[]|null
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $factory): array
    {
        return [
            $factory->create('created_at', 'desc'),
        ];
    }

    public function databaseNames(): array
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'reseller_id' => 'reseller_id',
            'logo_uri' => 'logo_uri',
            'documentation_uri' => 'documentation_uri',
            'description' => 'description',
            'script_template' => 'script_template',
            'vm_template' => 'vm_template',
            'platform' => 'platform',
            'active' => 'active',
            'publisher' => 'publisher',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }
}
