<?php

namespace App\Jobs\Instance;

use App\Jobs\TaskJob;
use App\Traits\V2\Jobs\Instance\ResolveHostGroup;

class AssociateHostGroup extends TaskJob
{
    use ResolveHostGroup;

    public function handle()
    {
        $instance = $this->task->resource;
        if ($hostGroup = $this->resolveHostGroupId()) {
            $instance->hostGroup()->associate($hostGroup);
            $instance->saveQuietly();
        }
    }
}
