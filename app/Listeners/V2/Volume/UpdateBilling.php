<?php

namespace App\Listeners\V2\Volume;

use App\Events\V2\Sync\Updated;
use App\Models\V2\BillingMetric;
use App\Models\V2\Volume;
use App\Support\Resource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateBilling
{
    /**
     * @param Updated $event
     * @return void
     * @throws \Exception
     */
    public function handle(Updated $event)
    {
        if (!$event->model->completed) {
            return;
        }

        if (Resource::classFromId($event->model->resource_id) != Volume::class) {
            return;
        }

        $volume = Volume::findOrFail($event->model->resource_id);

        $time = Carbon::now();

        $currentActiveMetric = BillingMetric::getActiveByKey($volume, 'disk.capacity');

        if (!empty($currentActiveMetric)) {
            if ($currentActiveMetric->value == $volume->capacity) {
                return;
            }
            $currentActiveMetric->end = $time;
            $currentActiveMetric->save();
        }

        $billingMetric = app()->make(BillingMetric::class);
        $billingMetric->resource_id = $volume->getKey();
        $billingMetric->vpc_id = $volume->vpc->getKey();
        $billingMetric->reseller_id = $volume->vpc->reseller_id;
        $billingMetric->key = 'disk.capacity';
        $billingMetric->value = $volume->capacity;
        $billingMetric->start = $time;

        $product = $volume->availabilityZone->products()->get()->firstWhere('name', 'volume');
        if (empty($product)) {
            Log::error(
                'Failed to load \'volume\' billing product for availability zone ' . $volume->availabilityZone->getKey()
            );
        } else {
            $billingMetric->category = $product->category;
            $billingMetric->price = $product->getPrice($volume->vpc->reseller_id);
        }

        $billingMetric->save();
    }
}
