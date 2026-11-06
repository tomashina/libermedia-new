<?php

namespace App\Http\Middleware;

use App\Services\Import\HerreraLegacyUrlService;
use App\Support\Libermedia\PublicUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveHerreraLegacyUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $result = app(HerreraLegacyUrlService::class)->resolve($request);
        if ($result) {
            if ($result['destination']) {
                $destination = $result['destination'];
                if (PublicUrls::enabled()) {
                    $parts = parse_url($destination);
                    parse_str($parts['query'] ?? '', $destinationQuery);
                    $query = array_diff_key($request->query(), array_flip([
                        'route', '_route_', 'product_id', 'manufacturer_id', 'information_id', 'blog_id', 'path', 'language', 'store_id',
                    ]));
                    if ($request->query('route') === 'product/search' && isset($query['search']) && is_string($query['search'])) {
                        $query['q'] = $query['search'];
                        unset($query['search']);
                    }
                    $query = array_replace($query, $destinationQuery);
                    $destination = ($parts['path'] ?? '/').($query !== [] ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '')
                        .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

                    return redirect()->away(PublicUrls::absolute($destination), 301);
                }

                return redirect($destination, 301);
            }

            if (PublicUrls::enabled()) {
                return response('<!doctype html><html lang="hr"><meta charset="utf-8"><title>Sadržaj više nije dostupan | Liber Media</title><main><h1>Sadržaj više nije dostupan</h1><p>Ova stara adresa pripada sadržaju koji više nije dostupan ili čeka provjeru.</p><a href="/trgovina">Pregledajte katalog</a></main></html>', 410)->header('X-Robots-Tag', 'noindex');
            }

            return response('<!doctype html><html lang="hr"><meta charset="utf-8"><title>Sadržaj više nije dostupan | Herrera</title><main><h1>Sadržaj više nije dostupan</h1><p>Ova stara adresa označena je za provjeru ili pripada sadržaju koji više nije dostupan.</p><a href="/shop">Pregledajte Herrera katalog</a></main></html>', 410)->header('X-Robots-Tag', 'noindex');
        }

        return $next($request);
    }
}
