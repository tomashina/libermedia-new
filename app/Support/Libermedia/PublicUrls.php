<?php

namespace App\Support\Libermedia;

use App\Support\StorefrontTheme;

/** Canonical public addresses; stored slugs, route names and protocol URLs stay stable. */
class PublicUrls
{
    private const PREFIXES = [
        'pages/category' => 'stranice/kategorija',
        'categories' => 'kategorije',
        'category' => 'kategorija',
        'product' => 'proizvod',
        'manufacturer' => 'brend',
        'page' => 'stranica',
        'shop' => 'trgovina',
        'contact' => 'kontakt',
        'faq' => 'pitanja-i-odgovori',
    ];

    public static function enabled(): bool
    {
        try {
            return StorefrontTheme::usesLibermediaBrand();
        } catch (\Throwable) {
            // URL generation also runs before the settings tables exist.
            return strtolower((string) config('storefront.brand')) === 'libermedia'
                || (config('storefront.brand', 'auto') === 'auto'
                    && str_contains(strtolower((string) config('app.name')), 'libermedia'));
        }
    }

    public static function localizedUri(string $uri): string
    {
        foreach (self::PREFIXES as $from => $to) {
            if ($uri === $from || str_starts_with($uri, $from.'/')) {
                return $to.substr($uri, strlen($from));
            }
        }

        return $uri;
    }

    public static function isPublicPath(string $path): bool
    {
        $uri = ltrim($path, '/');
        foreach ([...array_keys(self::PREFIXES), ...array_values(self::PREFIXES)] as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** Avoid inheriting Symfony's /index.php base URL from an OpenCart request. */
    public static function absolute(string $path): string
    {
        $parts = parse_url((string) config('app.url'));
        $origin = is_array($parts) && isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : request()->getSchemeAndHttpHost();

        return $origin.'/'.ltrim($path, '/');
    }

    public static function url(string $url): string
    {
        if (! self::enabled() || ! preg_match('#^(?:/|https?://)#i', $url) || str_starts_with($url, '//')) {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return $url;
        }
        parse_str($parts['query'] ?? '', $query);
        if (array_key_exists('signature', $query)) {
            return $url;
        }
        if (isset($parts['host'])) {
            $host = strtolower($parts['host']);
            $hosts = array_filter([
                strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
                strtolower((string) request()->getHost()),
            ]);
            if (! in_array($host, $hosts, true)) {
                return $url;
            }
            $ports = array_filter([parse_url((string) config('app.url'), PHP_URL_PORT), request()->getPort(), 80, 443]);
            if (isset($parts['port']) && ! in_array($parts['port'], $ports, true)) {
                return $url;
            }
        }
        $originalPath = (string) ($parts['path'] ?? '/');
        $path = '/'.self::localizedUri(ltrim($originalPath, '/'));
        if ($path === $originalPath) {
            return $url;
        }
        $prefix = isset($parts['host'])
            ? ($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')) : '';

        return $prefix.$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /** Rewrite only rendered href attributes; scripts, styles and stored content are untouched. */
    public static function html(string $html): string
    {
        if (! self::enabled()) {
            return $html;
        }
        $parts = preg_split('~(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }
            $parts[$index] = preg_replace_callback('~<(?:a|area|link)\b(?:[^<>\'"]+|\'[^\']*\'|"[^"]*")*>~is', static function (array $tag): string {
                // Consume every quoted attribute so a literal href inside a title is not treated as a link.
                return preg_replace_callback('~([^\s=<>\'"]+)(\s*=\s*)([\'"])(.*?)\3~is', static function (array $match): string {
                    if (strcasecmp($match[1], 'href') !== 0) {
                        return $match[0];
                    }
                    $value = html_entity_decode($match[4], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $localized = self::url($value);

                    return $localized === $value ? $match[0]
                        : $match[1].$match[2].$match[3].htmlspecialchars($localized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$match[3];
                }, $tag[0]) ?? $tag[0];
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    /** Aliases are always registered, so route caching and runtime brand selection agree. */
    public static function registerAliases(): void
    {
        $router = app('router');
        foreach (iterator_to_array($router->getRoutes()) as $route) {
            $uri = self::localizedUri($route->uri());
            if ($uri === $route->uri()) {
                continue;
            }
            $alias = clone $route;
            $alias->setUri($uri);
            $action = $alias->getAction();
            unset($action['prefix']);
            $action['libermedia_original_name'] = $route->getName();
            $action['as'] = 'libermedia.localized.'.($route->getName() ?: md5($route->uri()));
            $middleware = (array) ($action['middleware'] ?? []);
            $webPosition = array_search('web', $middleware, true);
            array_splice($middleware, $webPosition === false ? 0 : $webPosition + 1, 0, [\App\Http\Middleware\RequireLibermediaStorefront::class]);
            $action['middleware'] = $middleware;
            $alias->setAction($action);
            $router->getRoutes()->add($alias);
        }
    }
}
