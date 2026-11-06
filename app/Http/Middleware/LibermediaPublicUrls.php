<?php

namespace App\Http\Middleware;

use App\Support\Libermedia\PublicUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LibermediaPublicUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PublicUrls::enabled()) {
            return $next($request);
        }
        if (in_array($request->method(), ['GET', 'HEAD'], true) && ! $request->query->has('signature')) {
            $path = $request->getPathInfo();
            $target = PublicUrls::url($path);
            $scriptPrefix = str_ends_with($request->getBaseUrl(), '/index.php') && PublicUrls::isPublicPath($target);
            if ($target !== $path || $scriptPrefix) {
                $query = $request->getQueryString();

                return redirect()->away(PublicUrls::absolute($target.($query ? '?'.$query : '')), 301);
            }
        }

        $response = $next($request);
        if ($request->isMethod('GET') && $response->isSuccessful()
            && ! preg_match('#^/(?:admin|api|livewire)(?:/|$)#', $request->getPathInfo())
            && str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')
            && is_string($content = $response->getContent())) {
            $localized = PublicUrls::html($content);
            if ($localized !== $content) {
                $originalView = $response instanceof \Illuminate\Http\Response ? $response->getOriginalContent() : null;
                $response->setContent($localized);
                if ($response instanceof \Illuminate\Http\Response) {
                    $response->original = $originalView;
                }
                $response->headers->remove('Content-Length');
            }
        }

        return $response;
    }
}
