<?php

namespace App\Http\Middleware;

use App\Support\DeviceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDeviceCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->cookie(DeviceContext::COOKIE)) {
            $cookie = cookie()->forever(DeviceContext::COOKIE, DeviceContext::id(), null, null, null, false, true);
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
