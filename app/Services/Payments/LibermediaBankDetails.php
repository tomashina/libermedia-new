<?php

namespace App\Services\Payments;

final class LibermediaBankDetails
{
    /** Explicit settings win; another bank account cannot inherit parsed identity. */
    public static function settings(array $settings, ?string $description): array
    {
        $derived = self::fromDescription($description);
        $iban = strtoupper(preg_replace('/\s+/', '', (string) ($settings['upi_receiver_iban'] ?? '')));
        if ($iban !== '' && $iban !== ($derived['upi_receiver_iban'] ?? '')) {
            $instructions = $derived['bank_instructions'] ?? '';
            $derived = preg_match('/\b(?:IBAN|[A-Z]{2}\s*\d{2})/iu', $instructions)
                ? [] : ['bank_instructions' => $instructions];
        }

        return array_replace($derived, array_filter($settings, static fn ($value): bool => $value !== null && $value !== ''));
    }

    /** Read only explicit payment details from the imported public instructions. */
    public static function fromDescription(?string $description): array
    {
        $text = self::plainText($description ?? '');
        $result = ['bank_instructions' => $text];
        $lines = array_values(array_filter(explode("\n", $text), static fn (string $line): bool => $line !== ''));
        $ibans = [];
        $recipients = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/\bIBAN(?:\s+računa)?\s*:\s*(.*)$/iu', $line, $matches)) {
                $iban = strtoupper(preg_replace('/\s+/u', '', $matches[1] !== '' ? $matches[1] : ($lines[$index + 1] ?? '')));
                if (! self::validIban($iban)) {
                    return $result;
                }
                $ibans[$iban] = true;
            }
            if (preg_match('/^Primatelj(?:\s+uplate)?\s*:\s*(.*)$/iu', $line, $matches)) {
                $nameIndex = $matches[1] !== '' ? $index : $index + 1;
                $name = $matches[1] !== '' ? $matches[1] : ($lines[$nameIndex] ?? '');
                $recipients[] = ['name' => $name, 'address_index' => $nameIndex + 1];
            }
        }

        if (count($ibans) !== 1 || count($recipients) !== 1) {
            return $result;
        }
        $iban = array_key_first($ibans);
        // An additional account anywhere in the instructions makes an automatic
        // choice unsafe, even when that account has no explicit IBAN label.
        preg_match_all('/\bHR(?:\h*\d){19}(?!\h*\d)/iu', $text, $allAccounts);
        foreach ($allAccounts[0] as $account) {
            if (strtoupper(preg_replace('/\s+/u', '', $account)) !== $iban) {
                return $result;
            }
        }

        $recipient = $recipients[0];
        $name = trim($recipient['name']);
        if ($name === '' || mb_strlen($name) > 70 || preg_match('/^(?:IBAN|SWIFT|BIC|OIB|MBS|Banka)\b/iu', $name)) {
            return $result;
        }
        $address = self::address($lines, $recipient['address_index']);
        if ($address === null) {
            return $result;
        }

        return $result + [
            'upi_receiver_name' => $name,
            'upi_receiver_street' => $address['street'],
            'upi_receiver_place' => $address['place'],
            'upi_receiver_iban' => $iban,
        ];
    }

    private static function plainText(string $description): string
    {
        for ($pass = 0; $pass < 2; $pass++) {
            $description = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $description = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~isu', '', $description) ?? '';
        $description = preg_replace('~<(?:script|style)\b[^>]*>.*$~isu', '', $description) ?? '';
        $description = preg_replace('~<(?:br\s*/?|/(?:p|div|li|h[1-6]))\s*>~iu', "\n", $description) ?? '';
        $description = str_replace(["\r\n", "\r", "\u{00a0}"], ["\n", "\n", ' '], strip_tags($description));
        $description = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $description) ?? '';
        $lines = array_map(static fn (string $line): string => trim(preg_replace('/\h+/u', ' ', $line) ?? ''), explode("\n", $description));

        return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)) ?? '');
    }

    private static function address(array $lines, int $index): ?array
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^(.+?),\s*([^,]+),\s*(\d{5})$/u', $line, $parts)) {
            [$street, $city, $postal] = [trim($parts[1]), trim($parts[2]), $parts[3]];
        } elseif (preg_match('/^(.+?),\s*(?:HR-)?(\d{5})\s+([^,]+)$/u', $line, $parts)) {
            [$street, $city, $postal] = [trim($parts[1]), trim($parts[3]), $parts[2]];
        } elseif (preg_match('/^(?:HR-)?(\d{5})\s+([^,]+)$/u', $lines[$index + 1] ?? '', $parts)) {
            [$street, $city, $postal] = [trim($line), trim($parts[2]), $parts[1]];
        } else {
            return null;
        }

        if (! preg_match('/\pL/u', $street) || ! preg_match('/\d/u', $street)
            || ! preg_match('/\pL/u', $city) || preg_match('/[:<>]/u', $street.$city)) {
            return null;
        }

        return ['street' => $street, 'place' => $postal.' '.$city];
    }

    private static function validIban(string $iban): bool
    {
        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $iban)
            || (str_starts_with($iban, 'HR') && strlen($iban) !== 21)) {
            return false;
        }
        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
