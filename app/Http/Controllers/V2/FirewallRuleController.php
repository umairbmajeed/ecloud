<?php

namespace App\Http\Controllers\V2;

use App\Http\Requests\V2\FirewallRule\Create;
use App\Http\Requests\V2\FirewallRule\Update;
use App\Models\V2\FirewallRule;
use App\Models\V2\FirewallRulePort;
use App\Resources\V2\FirewallRulePortResource;
use App\Resources\V2\FirewallRuleResource;
use Illuminate\Http\Request;
use UKFast\DB\Ditto\QueryTransformer;

/**
 * Class FirewallRuleController
 * @package App\Http\Controllers\V2
 */
class FirewallRuleController extends BaseController
{
    /**
     * @param Request $request
     * @param QueryTransformer $queryTransformer
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Support\HigherOrderTapProxy|mixed
     */
    public function index(Request $request, QueryTransformer $queryTransformer)
    {
        $collection = FirewallRule::forUser($request->user);

        $queryTransformer->config(FirewallRule::class)
            ->transform($collection);

        return FirewallRuleResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string $firewallRuleId
     * @return FirewallRuleResource
     */
    public function show(Request $request, string $firewallRuleId)
    {
        return new FirewallRuleResource(
            FirewallRule::forUser($request->user)->findOrFail($firewallRuleId)
        );
    }

    public function ports(Request $request, QueryTransformer $queryTransformer, string $firewallRuleId)
    {
        $collection = FirewallRule::forUser($request->user)->findOrFail($firewallRuleId)->firewallRulePorts();
        $queryTransformer->config(FirewallRulePort::class)
            ->transform($collection);

        return FirewallRulePortResource::collection($collection->paginate(
            $request->input('per_page', env('PAGINATION_LIMIT'))
        ));
    }

    /**
     * @param Create $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function store(Create $request)
    {
        $firewallRule = new FirewallRule();
        $firewallRule->fill($request->only([
            'name',
            'sequence',
            'deployed',
            'firewall_policy_id',
            'source',
            'destination',
            'action',
            'direction',
            'enabled'
        ]));
        $firewallRule->save();

        if ($request->has('ports')) {
            foreach ($request->input('ports') as $port) {
                $port['firewall_rule_id'] = $firewallRule->id;
                $firewallRulePort = new FirewallRulePort($port);
                $firewallRulePort->save();
            }
        }

        return $this->responseIdMeta($request, $firewallRule->id, 201);
    }

    /**
     * @param Update $request
     * @param string $firewallRuleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Update $request, string $firewallRuleId)
    {
        $firewallRule = FirewallRule::foruser(app('request')->user)->findOrFail($firewallRuleId);
        $firewallRule->fill($request->only([
            'name',
            'sequence',
            'deployed',
            'firewall_policy_id',
            'source',
            'destination',
            'action',
            'direction',
            'enabled'
        ]));
        $firewallRule->save();

        if ($request->has('ports') &&
            (is_array($request->get('ports') && count($request->get('ports') > 0)))) {
            $firewallRule->firewallRulePorts->each(function ($rule) {
                $rule->delete();
            });
            foreach ($request->input('ports') as $port) {
                $port['firewall_rule_id'] = $firewallRule->id;
                $firewallRulePort = new FirewallRulePort($port);
                $firewallRulePort->save();
            }
        }

        return $this->responseIdMeta($request, $firewallRule->id, 200);
    }

    public function destroy(Request $request, string $firewallRuleId)
    {
        FirewallRule::foruser(app('request')->user)->findOrFail($firewallRuleId)
            ->delete();
        return response()->json([], 204);
    }
}
