<?php

namespace App\Http\Controllers\V2;

use App\Events\V2\Data\InstanceDeployEventData;
use App\Events\V2\Instance\Deploy;
use App\Events\V2\Instance\Deploy\Data;
use App\Events\V2\InstanceDeployEvent;
use App\Http\Requests\V2\Instance\CreateRequest;
use App\Http\Requests\V2\Instance\UpdateRequest;
use App\Jobs\Instance\GuestRestart;
use App\Jobs\Instance\GuestShutdown;
use App\Jobs\Instance\PowerOff;
use App\Jobs\Instance\PowerOn;
use App\Jobs\Instance\PowerReset;
use App\Jobs\Instance\UpdateTaskJob;
use App\Models\V2\Credential;
use App\Models\V2\Instance;
use App\Models\V2\Nic;
use App\Models\V2\Volume;
use App\Models\V2\Vpc;
use App\Resources\V2\CredentialResource;
use App\Resources\V2\InstanceResource;
use App\Resources\V2\NicResource;
use App\Resources\V2\VolumeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\HigherOrderTapProxy;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class InstanceController
 * @package App\Http\Controllers\V2
 */
class InstanceController extends BaseController
{
    /**
     * Get instance collection
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @return Response
     */
    public function index(Request $request, QueryTransformer $queryTransformer)
    {
        $collection = Instance::forUser($request->user);

        $queryTransformer->config(Instance::class)
            ->transform($collection);

        return InstanceResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Request $request
     * @param string $instanceId
     * @return InstanceResource
     */
    public function show(Request $request, string $instanceId)
    {
        $instance = Instance::forUser($request->user)->findOrFail($instanceId);
        if ($this->isAdmin) {
            $instance->makeVisible('appliance_version_id');
        }

        return new InstanceResource(
            $instance
        );
    }

    /**
     * @param CreateRequest $request
     * @return JsonResponse
     */
    public function store(CreateRequest $request)
    {
        $vpc = Vpc::forUser(app('request')->user)->findOrFail($request->input('vpc_id'));

        // Use the default network if there is only one and no network_id was passed in
        $defaultNetworkId = null;
        if (!$request->has('network_id')) {
            if ($vpc->routers->count() == 1 && $vpc->routers->first()->networks->count() == 1) {
                $defaultNetworkId = $vpc->routers->first()->networks->first()->id;
            }
            if (!$defaultNetworkId) {
                return JsonResponse::create([
                    'errors' => [
                        [
                            'title' => 'Not Found',
                            'detail' => 'No network_id provided and could not find a default network',
                            'status' => 404,
                            'source' => 'network_id'
                        ]
                    ]
                ], 404);
            }
        }

        $instance = new Instance($request->only([
            'name',
            'vpc_id',
            'availability_zone_id',
            'vcpu_cores',
            'ram_capacity',
            'locked',
            'backup_enabled',
        ]));

        $instance->locked = $request->input('locked', false);
        if ($request->has('appliance_id')) {
            $instance->setApplianceVersionId($request->get('appliance_id'));
        }
        $instance->save();
        $instance->refresh();

        $instanceDeployData = new Data();
        $instanceDeployData->instance_id = $instance->id;
        $instanceDeployData->vpc_id = $instance->vpc->id;
        $instanceDeployData->volume_capacity = $request->input('volume_capacity', config('volume.capacity.min'));
        $instanceDeployData->network_id = $request->input('network_id', $defaultNetworkId);
        $instanceDeployData->floating_ip_id = $request->input('floating_ip_id');
        $instanceDeployData->requires_floating_ip = $request->input('requires_floating_ip', false);
        $instanceDeployData->appliance_data = $request->input('appliance_data');
        $instanceDeployData->user_script = $request->input('user_script');

        $task = $instance->createTask();
        event(new Deploy($task, $instanceDeployData));

        return $this->responseIdMeta($request, $instance->getKey(), 201);
    }

    /**
     * @param UpdateRequest $request
     * @param string $instanceId
     * @return JsonResponse
     */
    public function update(UpdateRequest $request, string $instanceId)
    {
        $instance = Instance::forUser(app('request')->user)->findOrFail($instanceId);

        $instance->fill($request->only([
            'name',
            'locked',
            'backup_enabled',
        ]))->save();

        $task = $instance->createTask();
        dispatch(new UpdateTaskJob($task, $instance, $request->all()));

        return $this->responseIdMeta($request, $instance->getKey(), 200);
    }

    /**
     * @param Request $request
     * @param string $instanceId
     * @return Response|JsonResponse
     */
    public function destroy(Request $request, string $instanceId)
    {
        $instance = Instance::forUser($request->user)->findOrFail($instanceId);
        try {
            $instance->delete();
        } catch (\Exception $e) {
            return $instance->getDeletionError($e);
        }
        return response('', 204);
    }

    /**
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @param string $instanceId
     *
     * @return AnonymousResourceCollection|HigherOrderTapProxy|mixed
     */
    public function credentials(Request $request, QueryTransformer $queryTransformer, string $instanceId)
    {
        $collection = Instance::forUser($request->user)->findOrFail($instanceId)->credentials();
        $queryTransformer->config(Credential::class)
            ->transform($collection);

        return CredentialResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @param string $instanceId
     * @return AnonymousResourceCollection|HigherOrderTapProxy|mixed
     */
    public function volumes(Request $request, QueryTransformer $queryTransformer, string $instanceId)
    {
        $collection = Instance::forUser($request->user)->findOrFail($instanceId)->volumes();
        $queryTransformer->config(Volume::class)
            ->transform($collection);

        return VolumeResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @param string $instanceId
     * @return AnonymousResourceCollection|HigherOrderTapProxy|mixed
     */
    public function nics(Request $request, QueryTransformer $queryTransformer, string $instanceId)
    {
        $collection = Instance::forUser($request->user)->findOrFail($instanceId)->nics();
        $queryTransformer->config(Nic::class)
            ->transform($collection);

        return NicResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function powerOn(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)
            ->findOrFail($instanceId);

        $task = $instance->createTask();
        $this->dispatch(new PowerOn($task, [
            'instance_id' => $instance->id,
            'vpc_id' => $instance->vpc->id
        ]));

        return response('', 202);
    }

    public function powerOff(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)
            ->findOrFail($instanceId);

        $this->dispatch(new PowerOff([
            'instance_id' => $instance->id,
            'vpc_id' => $instance->vpc->id
        ]));


        return response('', 202);
    }

    public function guestRestart(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)
            ->findOrFail($instanceId);

        $this->dispatch(new GuestRestart([
            'instance_id' => $instance->id,
            'vpc_id' => $instance->vpc->id
        ]));

        return response('', 202);
    }

    public function guestShutdown(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)
            ->findOrFail($instanceId);

        $this->dispatch(new GuestShutdown([
            'instance_id' => $instance->id,
            'vpc_id' => $instance->vpc->id
        ]));

        return response('', 202);
    }

    public function powerReset(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)
            ->findOrFail($instanceId);

        $this->dispatch(new PowerReset([
            'instance_id' => $instance->id,
            'vpc_id' => $instance->vpc->id
        ]));

        return response('', 202);
    }

    public function lock(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)->findOrFail($instanceId);
        $instance->locked = true;
        $instance->save();

        return response('', 204);
    }

    public function unlock(Request $request, $instanceId)
    {
        $instance = Instance::forUser($request->user)->findOrFail($instanceId);
        $instance->locked = false;
        $instance->save();

        return response('', 204);
    }
}
