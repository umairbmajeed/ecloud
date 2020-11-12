<?php

namespace App\Models\V2;

use App\Events\V2\Vpc\Created;
use App\Events\V2\Vpc\Creating;
use App\Traits\V2\CustomKey;
use App\Traits\V2\DefaultName;
use App\Traits\V2\DeletionRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use UKFast\DB\Ditto\Factories\FilterFactory;
use UKFast\DB\Ditto\Factories\SortFactory;
use UKFast\DB\Ditto\Filter;
use UKFast\DB\Ditto\Filterable;
use UKFast\DB\Ditto\Sortable;

class Vpc extends Model implements Filterable, Sortable
{
    use CustomKey, SoftDeletes, DefaultName, DeletionRules;

    public $keyPrefix = 'vpc';
    public $incrementing = false;
    public $timestamps = true;
    protected $keyType = 'string';
    protected $connection = 'ecloud';
    protected $fillable = [
        'id',
        'name',
        'reseller_id',
        'region_id',
    ];

    protected $dispatchesEvents = [
        'creating' => Creating::class,
        'created' => Created::class,
    ];

    public $children = [
        'routers',
        'instances',
        'floatingIps',
        'loadBalancerClusters',
        'volumes',
    ];

    public function dhcp()
    {
        return $this->hasOne(Dhcp::class);
    }

    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function volumes()
    {
        return $this->hasMany(Volume::class);
    }

    public function floatingIps()
    {
        return $this->hasMany(FloatingIp::class);
    }

    public function loadBalancerClusters()
    {
        return $this->hasMany(LoadBalancerCluster::class);
    }

    /**
     * @param $query
     * @param $user
     * @return mixed
     */
    public function scopeForUser($query, $user)
    {
        if (!empty($user->resellerId)) {
            $resellerId = filter_var($user->resellerId, FILTER_SANITIZE_NUMBER_INT);
            if (!empty($resellerId)) {
                $query->where('reseller_id', '=', $resellerId);
            }
        }
        return $query;
    }

    /**
     * @param FilterFactory $factory
     * @return array|Filter[]
     */
    public function filterableColumns(FilterFactory $factory)
    {
        return [
            $factory->create('id', Filter::$stringDefaults),
            $factory->create('name', Filter::$stringDefaults),
            $factory->create('reseller_id', Filter::$stringDefaults),
            $factory->create('region_id', Filter::$stringDefaults),
            $factory->create('created_at', Filter::$dateDefaults),
            $factory->create('updated_at', Filter::$dateDefaults),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort[]
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function sortableColumns(SortFactory $factory)
    {
        return [
            $factory->create('id'),
            $factory->create('name'),
            $factory->create('reseller_id'),
            $factory->create('region_id'),
            $factory->create('created_at'),
            $factory->create('updated_at'),
        ];
    }

    /**
     * @param SortFactory $factory
     * @return array|\UKFast\DB\Ditto\Sort|\UKFast\DB\Ditto\Sort[]|null
     * @throws \UKFast\DB\Ditto\Exceptions\InvalidSortException
     */
    public function defaultSort(SortFactory $factory)
    {
        return [
            $factory->create('name', 'asc'),
        ];
    }

    /**
     * @return array|string[]
     */
    public function databaseNames()
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'reseller_id' => 'reseller_id',
            'region_id' => 'region_id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }
}
