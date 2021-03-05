<?php

namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\CreateImageRequest;
use App\Http\Requests\V2\Image\StoreRequest;
use App\Http\Requests\V2\UpdateImageRequest;
use App\Jobs\Nsx\Image\Undeploy;
use App\Models\V2\Image;
use App\Resources\V2\ImageParameterResource;
use App\Resources\V2\ImageResource;
use Illuminate\Http\Request;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class ImageController
 * @package App\Http\Controllers\V2
 */
class ImageController extends BaseController
{
    public function index(Request $request, QueryTransformer $queryTransformer)
    {
        $collection = Image::query();

        $queryTransformer->config(Image::class)
            ->transform($collection);

        return ImageResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function show(string $imageId)
    {
        return new ImageResource(
            Image::findOrFail($imageId)
        );
    }

    public function parameters(Request $request, string $imageId)
    {
        $collection = Image::findOrFail($imageId)->parameters();

        return ImageParameterResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    public function store(StoreRequest $request)
    {
        $images = new Image($request->only(['appliance_version_id']));
        $images->save();
        $images->refresh();
        return $this->responseIdMeta($request, $images->id, 201);
    }

    public function destroy(string $imageId)
    {
        $model = Image::findOrFail($imageId);
        $model->delete();
        return response()->json([], 204);
    }
}
