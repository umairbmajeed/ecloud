<?php

namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\FloatingIp\AssignRequest;
use App\Http\Requests\V2\FloatingIp\CreateRequest;
use App\Http\Requests\V2\FloatingIp\UpdateRequest;
use App\Jobs\Tasks\FloatingIp\Assign;
use App\Jobs\Tasks\FloatingIp\Unassign;
use App\Models\V2\FloatingIp;
use App\Resources\V2\FloatingIpResource;
use App\Resources\V2\TaskResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class InstanceController
 * @package App\Http\Controllers\V2
 */
class FloatingIpController extends BaseController
{
    public function index(Request $request)
    {
        $collection = FloatingIp::forUser($request->user());

        return FloatingIpResource::collection(
            $collection->search()
                ->paginate(
                    $request->input('per_page', env('PAGINATION_LIMIT'))
                )
        );
    }

    public function show(Request $request, string $fipId)
    {
        return new FloatingIpResource(
            FloatingIp::forUser($request->user())->findOrFail($fipId)
        );
    }

    public function store(CreateRequest $request)
    {
//        $availabilityZone = AvailabilityZone::forUser(Auth::user())
//            ->findOrFail($request->availability_zone_id)
//            ->region_id;
//        $vpc = Vpc::forUser(Auth::user())->findOrFail($request->vpc_id)->region_id;
//
//        if ($availabilityZone !== $vpc) {
//            return response()->json([
//                'errors' => [
//                    'title' => 'Not Found',
//                    'detail' => 'The specified availability zone is not available to that VPC',
//                    'status' => 404,
//                    'source' => 'availability_zone_id'
//                ]
//            ], 404);
//        }

        $floatingIp = new FloatingIp(
            $request->only(['vpc_id', 'name', 'availability_zone_id', 'rdns_hostname'])
        );

        $task = $floatingIp->syncSave();
        return $this->responseIdMeta($request, $floatingIp->id, 202, $task->id);
    }

    public function update(UpdateRequest $request, string $fipId)
    {
        $floatingIp = FloatingIp::forUser(Auth::user())->findOrFail($fipId);
        $floatingIp->fill($request->only(['name', 'rdns_hostname']));

        $task = $floatingIp->syncSave();
        return $this->responseIdMeta($request, $floatingIp->id, 202, $task->id);
    }

    public function destroy(Request $request, string $fipId)
    {
        $floatingIp = FloatingIp::forUser($request->user())->findOrFail($fipId);

        $task = $floatingIp->syncDelete();
        return $this->responseTaskId($task->id);
    }

    public function assign(AssignRequest $request, string $fipId)
    {
        $floatingIp = FloatingIp::forUser($request->user())->findOrFail($fipId);

        $task = $floatingIp->createTaskWithLock(
            Assign::$name,
            Assign::class,
            ['resource_id' => $request->resource_id]
        );

        return $this->responseIdMeta($request, $floatingIp->id, 202, $task->id);
    }

    public function unassign(Request $request, string $fipId)
    {
        $floatingIp = FloatingIp::forUser($request->user())->findOrFail($fipId);

        $task = $floatingIp->createTaskWithLock(Unassign::$name, Unassign::class);

        return $this->responseIdMeta($request, $floatingIp->id, 202, $task->id);
    }

    public function tasks(Request $request, string $fipId)
    {
        $collection = FloatingIp::forUser($request->user())->findOrFail($fipId)->tasks();

        return TaskResource::collection($collection->search()->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }
}
