<?php

namespace App\Support;

use App\Models\Content\ContentBlock;
use App\Models\Content\ContentBlockTranslation;

class LibermediaWidgetSettings
{
    public static function definition(string $code): array
    {
        return (array) config('libermedia_widgets.'.$code, []);
    }

    public static function fields(string $code): array
    {
        return (array) (self::definition($code)['fields'] ?? []);
    }

    public static function forBlock(ContentBlock $block, ?ContentBlockTranslation $translation = null): array
    {
        $definition = self::definition((string) $block->code);
        if ($definition === []) {
            return [];
        }

        $settings = array_replace(
            ['title' => '', 'subtitle' => '', 'cta_label' => '', 'cta_url' => ''],
            (array) ($definition['content_defaults'] ?? []),
            array_map(static fn (array $field): mixed => $field['default'] ?? '', self::fields((string) $block->code)),
        );
        $payload = is_array($translation?->payload) ? $translation->payload : [];
        $configured = array_key_exists('libermedia', $payload);
        $stored = is_array($payload['libermedia'] ?? null) ? $payload['libermedia'] : [];

        if ($definition['translation_content'] ?? false) {
            $settings['title'] = (string) ($translation?->title ?? $block->name);
            $settings['cta_label'] = (string) ($translation?->cta_label ?? '');
            $settings['cta_url'] = (string) ($translation?->cta_url ?? '');
        }

        // Static instances previously ignored translation titles. Keep their visible
        // copy until an editor first saves the structured widget settings.
        foreach ((array) ($definition['content_fields'] ?? []) as $key) {
            if ($translation !== null && $configured) {
                $settings[$key] = (string) ($translation->{$key} ?? '');
            }
            if (array_key_exists($key, $stored) && is_scalar($stored[$key])) {
                $settings[$key] = (string) $stored[$key];
            }
        }

        foreach (self::fields((string) $block->code) as $key => $field) {
            $value = $stored[$key] ?? $settings[$key];
            $settings[$key] = match ($field['type'] ?? 'text') {
                'url', 'image' => self::safeUrl($value, $field['type'] === 'image'),
                'icon' => is_string($value) && preg_match('/^[a-z0-9-]{1,80}$/D', $value) ? $value : (string) ($field['default'] ?? ''),
                'checkbox' => filter_var($value, FILTER_VALIDATE_BOOL),
                default => is_scalar($value) ? (string) $value : (string) ($field['default'] ?? ''),
            };
        }
        $settings['cta_url'] = self::safeUrl($settings['cta_url']);

        return $settings;
    }

    public static function safeUrl(mixed $value, bool $image = false): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim($value);
        if ($value === '' || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return '';
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }
        if (! $image && str_starts_with($value, '#')) {
            return $value;
        }
        $url = parse_url($value);
        if ($url === false) {
            return '';
        }
        $scheme = strtolower((string) ($url['scheme'] ?? ''));
        if ($scheme === 'https' && ! empty($url['host']) && ! isset($url['user']) && ! isset($url['pass'])) {
            return $value;
        }
        if (! $image && in_array($scheme, ['tel', 'mailto'], true)) {
            return $value;
        }

        return '';
    }

    /** Escaped text with only attribute-free emphasis and line breaks. */
    public static function formattedText(string $value): string
    {
        return nl2br(str_replace(
            ['&lt;strong&gt;', '&lt;/strong&gt;', '&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'],
            ['<strong>', '</strong>', '<br>', '<br>', '<br>'],
            e($value),
        ), false);
    }
}
