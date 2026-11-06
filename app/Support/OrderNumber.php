<?php

namespace App\Support;

class OrderNumber
{
    public static function display(string $number): string
    {
        if (StorefrontTheme::usesLibermediaBrand() && preg_match('/^OC-HERRERA-(\d+)$/D', $number, $matches)) {
            return 'LM-'.$matches[1];
        }

        return $number;
    }

    public static function searchValue(string $value): string
    {
        if (StorefrontTheme::usesLibermediaBrand() && str_starts_with(strtoupper($value), 'LM-')) {
            return 'OC-HERRERA-'.substr($value, 3);
        }

        return $value;
    }

    public static function lookupCandidates(string $value): array
    {
        if (StorefrontTheme::usesLibermediaBrand() && preg_match('/^LM-(\d+)$/iD', $value, $matches)) {
            return [$value, 'OC-HERRERA-'.$matches[1]];
        }

        return [$value];
    }

    public static function checkoutPrefix(): string
    {
        return StorefrontTheme::usesLibermediaBrand() ? 'LM' : 'AG';
    }
}
