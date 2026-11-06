@php
    $lmWidget = \App\Support\LibermediaWidgetSettings::forBlock($block ?? new \App\Models\Content\ContentBlock(['code' => 'libermedia-home-benefits']), $translation ?? null);
    if ($lmWidget['auto_shipping']) {
        $shippingBenefit = app(\App\Support\LibermediaShippingBenefit::class)->forCroatia();
        $lmWidget['benefit_1_title'] = $shippingBenefit['title'];
        $lmWidget['benefit_1_description'] = $shippingBenefit['description'];
    }
@endphp
<section class="lm-benefits" aria-label="Prednosti kupnje" data-libermedia-home-benefits>
    @foreach (range(1, 4) as $lmBenefit)
        @if ($lmWidget['benefit_'.$lmBenefit.'_url'] !== '')
            <a href="{{ $lmWidget['benefit_'.$lmBenefit.'_url'] }}">
        @else
            <div>
        @endif
            <x-fa-icon :name="$lmWidget['benefit_'.$lmBenefit.'_icon']" style="light" :inline="true" /><span><strong>{{ $lmWidget['benefit_'.$lmBenefit.'_title'] }}</strong><small>{{ $lmWidget['benefit_'.$lmBenefit.'_description'] }}</small></span>
        @if ($lmWidget['benefit_'.$lmBenefit.'_url'] !== '')
            </a>
        @else
            </div>
        @endif
    @endforeach
</section>
