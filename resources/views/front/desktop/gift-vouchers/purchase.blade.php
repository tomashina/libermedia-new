@extends('front.desktop.layouts.store')
@section('title', 'Vaš poklon bon – potvrda narudžbe')
@section('main_class', 'gift-voucher-shell')
@push('styles')
    <link rel="stylesheet" href="{{ asset('front-theme/styles/gift-vouchers.css') }}?v={{ filemtime(public_path('front-theme/styles/gift-vouchers.css')) }}">
@endpush
@section('content')
    <header class="lm-page-title">
        <p class="gift-voucher-eyebrow"><x-fa-icon name="gift" /> VAŠ POKLON BON</p>
        <h1>{{ $order->status?->is_cancelled ? 'Narudžba poklon bona je otkazana.' : ($order->paid_at ? 'Hvala što darujete igru i veselje.' : 'Vaš poklon čeka potvrdu plaćanja.') }}</h1>
        <p>Narudžba <strong>{{ $order->display_order_number }}</strong></p>
    </header>
    <section class="gift-voucher-receipt gift-voucher-form-card">
        @if($order->status?->is_cancelled)
            <p class="gift-voucher-field-error">Narudžba je otkazana. Poklon bon nije dostupan za korištenje.</p>
        @elseif($order->paid_at)
            <p>Uplata je potvrđena. {{ $voucher->email_sent_at ? 'Digitalni poklon bon poslan je primatelju.' : 'Digitalni poklon bon pripremamo za slanje primatelju.' }}</p>
        @else
            <p>Nakon potvrde uplate aktivirat ćemo bon i poslati ga na e-mail primatelja.</p>
        @endif
        <dl class="gift-voucher-receipt-details">
            <div><dt>Vrijednost bona</dt><dd>{{ \App\Support\Currency::format((float) $voucher->initial_amount) }}</dd></div>
            <div><dt>Ukupno za uplatu</dt><dd>{{ \App\Support\Currency::format((float) $order->grand_total) }}</dd></div>
            <div><dt>Primatelj</dt><dd>{{ $voucher->recipient_name }}<small>{{ $voucher->recipient_email }}</small></dd></div>
            <div><dt>Status</dt><dd>{{ $order->status?->display_name ?: ($order->paid_at ? 'Plaćeno' : 'Čeka uplatu') }}</dd></div>
        </dl>
        @if(!$order->paid_at && !$order->status?->is_cancelled && !empty($bankTransfer))
            <section class="gift-voucher-bank-instructions">
                <h2>Podaci za uplatu</h2>
                @if(!empty($bankTransfer['instructions']))<p class="gift-voucher-bank-note">{{ $bankTransfer['instructions'] }}</p>@endif
                @if(!empty($bankTransfer['receiver_iban']))
                    <dl><div><dt>Primatelj uplate</dt><dd>{{ $bankTransfer['receiver_name'] }}</dd></div><div><dt>IBAN</dt><dd>{{ $bankTransfer['receiver_iban'] }}</dd></div><div><dt>Model i poziv na broj</dt><dd>HR{{ preg_replace('/^HR/i', '', (string) ($bankTransfer['model'] ?? '00')) }} {{ $bankTransfer['reference'] }}</dd></div><div><dt>Iznos</dt><dd>{{ \App\Support\Currency::format((float) $bankTransfer['amount']) }}</dd></div></dl>
                @endif
                @if(!empty($bankTransfer['qr_image_base64']))
                    <p>HUB3 barkod za mobilno bankarstvo</p>
                    <img src="data:{{ $bankTransfer['qr_image_mime'] ?: 'image/png' }};base64,{{ $bankTransfer['qr_image_base64'] }}" alt="Skenirajte podatke za uplatu" width="380" style="max-width:100%;height:auto">
                @endif
                @if(!empty($bankTransfer['epc_qr_image_base64']))
                    <p>SEPA QR kod</p>
                    <img src="data:image/png;base64,{{ $bankTransfer['epc_qr_image_base64'] }}" alt="SEPA QR kod za uplatu" width="240" style="max-width:100%;height:auto">
                @endif
            </section>
        @endif
        <div class="gift-voucher-receipt-actions">
            @if($paymentUrl)<a href="{{ $paymentUrl }}" class="commerce-primary-action">Nastavi na plaćanje <x-fa-icon name="arrow-right" /></a>@endif
            <a href="{{ route('shop.index') }}" class="commerce-secondary-action">Istražite ponudu</a>
        </div>
    </section>
@endsection
