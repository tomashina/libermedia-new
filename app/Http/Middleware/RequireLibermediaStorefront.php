<?php

namespace App\Http\Middleware;

use App\Support\Libermedia\PublicUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLibermediaStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(PublicUrls::enabled(), 404);
        $route = $request->route();
        $originalAction = $route?->getAction();
        $name = $route?->getAction('libermedia_original_name');
        if ($name) {
            $route->setAction(array_replace($originalAction, ['as' => $name]));
        }

        try {
            return $next($request);
        } finally {
            if ($name) {
                $route->setAction($originalAction);
            }
        }
    }
}
