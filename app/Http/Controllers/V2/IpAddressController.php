<?php
namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\IpAddress\CreateRequest;
use App\Http\Requests\V2\IpAddress\UpdateRequest;
use App\Models\V2\IpAddress;
use App\Models\V2\Nic;
use App\Resources\V2\IpAddressResource;
use App\Resources\V2\NicResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use UKFast\DB\Ditto\QueryTransformer;

class IpAddressController extends BaseController
{
    public function index(Request $request)
    {
        $collection = IpAddress::forUser($request->user());

        (new QueryTransformer($request))
            ->config(IpAddress::class)
            ->transform($collection);

        return IpAddressResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function show(Request $request, string $ipAddressId)
    {
        return new IpAddressResource(
            IpAddress::forUser($request->user())->findOrFail($ipAddressId)
        );
    }

    public function store(CreateRequest $request)
    {
        $ipAddress = new IpAddress(
            $request->only([
                'name',
                'ip_address',
                'type'
            ])
        );
        $ipAddress->save();
        return $this->responseIdMeta($request, $ipAddress->id, 201);
    }

    public function update(UpdateRequest $request, string $ipAddressId)
    {
        $ipAddress = IpAddress::forUser(Auth::user())->findOrFail($ipAddressId);

        $ipAddress->fill($request->only([
            'name',
            'ip_address',
            'type'
        ]));
        $ipAddress->save();

        return $this->responseIdMeta($request, $ipAddress->id, 200);
    }

    public function destroy(Request $request, string $ipAddressId)
    {
        $ipAddress = IpAddress::forUser($request->user())->findOrFail($ipAddressId);
        $ipAddress->delete();
        // TODO: remove from pivot table
        return response('', 204);
    }

    public function nics(Request $request, QueryTransformer $queryTransformer, string $ipAddressId)
    {
        $collection = IpAddress::forUser($request->user())->findOrFail($ipAddressId)->nics();
        $queryTransformer->config(Nic::class)
            ->transform($collection);

        return NicResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }
}
