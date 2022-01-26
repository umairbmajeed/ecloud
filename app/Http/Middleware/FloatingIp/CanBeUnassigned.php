<?php

namespace App\Http\Middleware\FloatingIp;

use App\Models\V2\FloatingIp;
use App\Models\V2\IpAddress;
use App\Models\V2\VpnEndpoint;
use Closure;
use Illuminate\Support\Facades\Auth;

class CanBeUnassigned
{
    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $floatingIp = FloatingIp::forUser(Auth::user())->findOrFail($request->route('fipId'));

        $detail = null;

        if (!empty($floatingIp->resource_id) && $floatingIp->resource instanceof VpnEndpoint) {
            $detail = 'Floating IP\'s can not be unassigned from a VPN endpoint';
        }

        if (!empty($floatingIp->resource_id) && $floatingIp->resource instanceof IpAddress) {
            if ($floatingIp->resource->vip()->exists()) {
                $detail = 'Floating IP\'s can not be unassigned from a VIP';
            }
        }

        if ($detail) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => $detail,
                        'status' => 403,
                    ]
                ]
            ], 403);
        }

        return $next($request);
    }
}
