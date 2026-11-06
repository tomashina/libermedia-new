<?php

namespace App\Support;

use App\Models\Settings\Local\OrderStatus;
use Illuminate\Support\Str;

class OrderStatusDisplay
{
    private const LABELS = [
        'new' => 'Nova',
        'pending' => 'U obradi',
        'processing' => 'U pripremi',
        'processed' => 'Obrađena',
        'shipped' => 'Poslana',
        'sent' => 'Poslana',
        'complete' => 'Dovršena',
        'completed' => 'Dovršena',
        'delivered' => 'Dostavljena',
        'paid' => 'Plaćena',
        'awaitingpayment' => 'Čeka uplatu',
        'paymentpending' => 'Čeka uplatu',
        'unpaid' => 'Čeka uplatu',
        'awaitingshipment' => 'Čeka slanje',
        'ready' => 'Spremna za slanje',
        'onhold' => 'Na čekanju',
        'cancelled' => 'Otkazana',
        'canceled' => 'Otkazana',
        'canceledreversal' => 'Poništen povrat sredstava',
        'cancelledreversal' => 'Poništen povrat sredstava',
        'chargeback' => 'Povrat terećenja',
        'denied' => 'Odbijena',
        'expired' => 'Istekla',
        'failed' => 'Neuspjela',
        'refunded' => 'Sredstva vraćena',
        'partiallyrefunded' => 'Djelomično vraćena sredstva',
        'reversed' => 'Stornirana',
        'voided' => 'Poništena',
        'returned' => 'Vraćena',
    ];

    public static function label(string $name, string $code = ''): string
    {
        if (! StorefrontTheme::usesLibermediaBrand()) {
            return $name;
        }
        $key = self::key($name !== '' ? $name : $code);

        // Known imported English labels are translated; custom labels remain intact.
        return self::LABELS[$key] ?? $name;
    }

    public static function isRegularStep(OrderStatus $status): bool
    {
        if ($status->is_cancelled) {
            return false;
        }
        $exceptions = ['cancelled', 'canceled', 'canceledreversal', 'cancelledreversal', 'chargeback', 'denied',
            'expired', 'failed', 'refunded', 'partiallyrefunded', 'reversed', 'voided', 'returned',
            'otkazano', 'otkazana', 'stornirano', 'stornirana', 'odbijena', 'istekla', 'neuspjela', 'vracena', 'ponistena'];

        return ! in_array(self::key((string) $status->name), $exceptions, true)
            && ! in_array(self::key((string) $status->code), $exceptions, true);
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii(trim($value)))) ?? '';
    }
}
