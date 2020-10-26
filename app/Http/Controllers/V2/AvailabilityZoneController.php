<?php

namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\CreateAvailabilityZoneRequest;
use App\Http\Requests\V2\UpdateAvailabilityZoneRequest;
use App\Models\V2\AvailabilityZone;
use App\Resources\V2\AvailabilityZoneResource;
use App\Resources\V2\CredentialResource;
use App\Resources\V2\DhcpResource;
use App\Resources\V2\InstanceResource;
use App\Resources\V2\LoadBalancerClusterResource;
use App\Resources\V2\RouterResource;
use Illuminate\Http\Request;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class AvailabilityZoneController
 * @package App\Http\Controllers\V2
 */
class AvailabilityZoneController extends BaseController
{
    /**
     * Get availability zones collection
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, QueryTransformer $queryTransformer)
    {
        $collection = AvailabilityZone::forUser($request->user);
        $queryTransformer->config(AvailabilityZone::class)
            ->transform($collection);

        return AvailabilityZoneResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Request $request
     * @param string $zoneId
     * @return AvailabilityZoneResource
     */
    public function show(Request $request, string $zoneId)
    {
        return new AvailabilityZoneResource(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
        );
    }

    /**
     * @param CreateAvailabilityZoneRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(CreateAvailabilityZoneRequest $request)
    {
        $availabilityZone = new AvailabilityZone($request->only([
            'code',
            'name',
            'datacentre_site_id',
            'is_public',
            'region_id',
            'nsx_manager_endpoint',
            'nsx_edge_cluster_id',
        ]));
        $availabilityZone->save();
        $availabilityZone->refresh();
        return $this->responseIdMeta($request, $availabilityZone->getKey(), 201);
    }

    /**
     * @param UpdateAvailabilityZoneRequest $request
     * @param string $zoneId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAvailabilityZoneRequest $request, string $zoneId)
    {
        $availabilityZone = AvailabilityZone::findOrFail($zoneId);
        $availabilityZone->fill($request->only([
            'code',
            'name',
            'datacentre_site_id',
            'is_public',
            'region_id',
            'nsx_manager_endpoint',
            'nsx_edge_cluster_id',
        ]));
        $availabilityZone->save();
        return $this->responseIdMeta($request, $availabilityZone->getKey(), 200);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function routers(Request $request, string $zoneId)
    {
        return RouterResource::collection(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
                ->routers()
                ->paginate($request->input('per_page', env('PAGINATION_LIMIT')))
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function dhcps(Request $request, string $zoneId)
    {
        return DhcpResource::collection(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
                ->dhcps()
                ->paginate($request->input('per_page', env('PAGINATION_LIMIT')))
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function credentials(Request $request, string $zoneId)
    {
        return CredentialResource::collection(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
                ->credentials()
                ->paginate($request->input('per_page', env('PAGINATION_LIMIT')))
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function instances(Request $request, string $zoneId)
    {
        return InstanceResource::collection(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
                ->instances()
                ->paginate($request->input('per_page', env('PAGINATION_LIMIT')))
        );
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function lbcs(Request $request, string $zoneId)
    {
        return LoadBalancerClusterResource::collection(
            AvailabilityZone::forUser($request->user)->findOrFail($zoneId)
                ->loadBalancerClusters()
                ->paginate($request->input('per_page', env('PAGINATION_LIMIT')))
        );
    }

    /**
     * @param Request $request
     * @param string $zoneId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, string $zoneId)
    {
        $availabilityZone = AvailabilityZone::findOrFail($zoneId);
        $availabilityZone->delete();
        return response()->json([], 204);
    }
}
