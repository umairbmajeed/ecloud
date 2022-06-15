<?php

namespace App\Console\Commands\Nsx;

use App\Console\Commands\Command;
use App\Models\V2\AvailabilityZoneable;
use App\Models\V2\Network;
use App\Models\V2\Router;
use App\Models\V2\Vpc;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Str;

class UndeployOrphanedResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nsx:undeploy-orphaned-resources {--T|test-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Undeploy orphaned resources';

    public $markedForDeletion = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $routers = Router::all()->mapWithKeys(function ($item) {
            return [$item->id => $item];
        });

        if ($routers->count() > 0) {
            $this->processDeletion($routers, 'vpc');
        }

        $networks = Network::with(['router' => fn($q) => $q->withTrashed()])
            ->get()->mapWithKeys(function ($item) {
                return [$item->id => $item];
            });

        if ($networks->count() > 0) {
            $this->markedForDeletion = [];
            $this->processDeletion($networks, 'router');
        }

        return 0;
    }

    /**
     *
     * @param Iterable $resources
     * @param string $parent
     * @return void
     * @throws \Exception
     */
    protected function processDeletion(iterable $resources, string $parent): void
    {
        $resourceType = Str::plural(Str::afterLast($resources->first()::class, '\\'));

        $skipped = [];
        $failedDeletes = [];
        $syncDeletes = [];

        foreach ($resources as $resource) {
            if (str_ends_with($resource->id, 'aaaaaaaa')) {
                continue;
            }

            $existsOnNsx = 'No';
            $reason = null;

            // Has no tasks
            if (!$this->hasTasks($resource)) {
                $reason = 'No tasks';
            }

            // Has no parent resource
            if (!$resource->$parent()->exists()) {
                $reason = 'No ' . $parent;
            }

            if ($resource instanceof AvailabilityZoneable) {
                // Check if resource exists on NSX
                if ($reason) {
                    try {
                        $endpoint = match ($resource::class) {
                            Router::class => 'policy/api/v1/infra/tier-1s/' . $resource->id,
                            Network::class => 'policy/api/v1/infra/tier-1s/' . $resource?->router?->id . '/segments/' . $resource->id,
                        };

                        switch ($resource::class) {
                            case Network::class:
                                if (empty($resource->$parent)) {
                                    $existsOnNsx = 'Unknown - No soft deleted parent record found';
                                } else {
                                    if ($resource->availabilityZone == null || !$this->hasNsxCredentials($resource)) {
                                        $existsOnNsx = 'No';
                                    } else {
                                        $resource->availabilityZone->nsxService()->get($endpoint);
                                        $existsOnNsx = 'Yes';
                                    }
                                }
                                break;
                            case Router::class:
                                if ($resource->availabilityZone == null || !$this->hasNsxCredentials($resource)) {
                                    $existsOnNsx = 'No';
                                } else {
                                    $resource->availabilityZone->nsxService()->get($endpoint);
                                    $existsOnNsx = 'Yes';
                                }
                                break;
                        }
                    } catch (ClientException $e) {
                        if ($e->hasResponse() && $e->getResponse()->getStatusCode() != 404) {
                            $reason = null;
                            $skipped[] = [$resource->id, $resource->name, $e->getMessage()];
                        }
                    }
                }
            }

            if ($reason) {
                $this->markedForDeletion[] = [$resource->id, $resource->name, $reason, $existsOnNsx];
            }
        }

        if (count($skipped) > 0) {
            $this->warn($resourceType . ' Skipped');
            $this->table(
                ['ID', 'Name', 'Reason'],
                $skipped
            );
            $this->info(count($skipped) . ' ' . $resourceType . ' Skipped');
        }

        if (count($this->markedForDeletion) > 0) {
            $this->info($resourceType . ' Marked For Deletion');
            $this->table(
                ['ID', 'Name', 'Reason', 'Exists on NSX'],
                $this->markedForDeletion
            );

            $this->markedForDeletion = collect($this->markedForDeletion);

            $this->info('Total: ' . $this->markedForDeletion->count());
            $existsOnNsxTotal = $this->markedForDeletion->filter(function ($item) {
                return $item[3] === 'Yes';
            })->count();
            $this->info('Total with resources on NSX: ' . $existsOnNsxTotal);

            $undetermined = $this->markedForDeletion->filter(function ($item) {
                return $item[3] === 'Unknown - No soft deleted parent record found';
            })->count();
            $this->info('Total with undetermined resources on NSX: ' . $undetermined);

            if ($this->confirm('Undeploy orphaned ' . strtolower($resourceType) . ' from NSX?', true)) {
                $deleted = 0;

                foreach ($this->markedForDeletion as [$id, $name, $reason, $exists]) {
                    if ($exists === 'Yes') {
                        $this->info('Undeploying ' . $id);
                        if (!$this->option('test-run')) {
                            $resource = $resources->get($id);

                            if ($resource) {
                                $resource->setAttribute('deleted_at', null)->saveQuietly();
                                if ($resource::class == Router::class) {
                                    $vpc = Vpc::withTrashed()->find($resource->vpc_id);
                                    if ($vpc) {
                                        $vpc->setAttribute('deleted_at', null)->saveQuietly();
                                    }
                                    $resource->refresh();
                                    $task = $resource->syncDelete();
                                    $vpc->delete();
                                    $syncDeletes[] = [$resource->id, $task->id];
                                } else {
                                    $router = Router::withTrashed()->find($resource->router_id);
                                    if ($router) {
                                        $router->setAttribute('deleted_at', null)->saveQuietly();
                                        $vpc = Vpc::withTrashed()->find($router->vpc_id);
                                        $vpc->setAttribute('deleted_at', null)->saveQuietly();
                                        $router->load('availabilityZone');
                                        $resource->load('router');
                                        $task = $resource->syncDelete();
                                        $router->delete();
                                        $vpc->delete();
                                        $syncDeletes[] = [$resource->id, $task->id];
                                    }
                                }
                            }
                        }
                        $deleted++;
                    }
                }
                $this->info('Total ' . $deleted . ' Undeploys Attempted');
                if (count($syncDeletes) > 0) {
                    $this->info(count($syncDeletes) . ' delete syncs in progress');
                    $this->table(
                        ['ID', 'Task ID'],
                        $syncDeletes
                    );
                }
                if (count($failedDeletes) > 0) {
                    $this->info(count($failedDeletes) . ' undeploys failed');
                    $this->table(
                        ['ID', 'Name', 'Reason'],
                        $failedDeletes
                    );
                }
            } else {
                $this->info('Records were not deleted');
            }
        } else {
            $this->info($resourceType . ' marked For Deletion: 0');
        }

        $this->line('----------------------------------------------------------');
    }

    protected function hasNsxCredentials(Network|Router $resource): bool
    {
        return $resource->availabilityZone->credentials()
            ->where('name', '=', 'NSX')
            ->count() > 0;
    }

    /**
     * @param $resource
     * @return bool
     */
    protected function hasTasks($resource)
    {
        return $resource->tasks()->count() > 0;
    }
}
