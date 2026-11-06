@php($lmWidget = \App\Support\LibermediaWidgetSettings::forBlock($block ?? new \App\Models\Content\ContentBlock(['code' => 'libermedia-home-campaigns']), $translation ?? null))
<section class="lm-campaign-section" aria-labelledby="lm-campaigns-title" data-libermedia-campaigns>
    <header class="lm-section-heading"><div><p class="lm-eyebrow">{{ $lmWidget['subtitle'] }}</p><h2 id="lm-campaigns-title">{{ $lmWidget['title'] }}</h2></div></header>
    <div class="lm-campaign-grid">
        <article class="lm-campaign lm-campaign--bags">
            <div class="lm-campaign-copy">
                <p class="lm-eyebrow">{{ $lmWidget['bag_eyebrow'] }}</p>
                <h3>{!! nl2br(e($lmWidget['bag_title']), false) !!}</h3>
                <p>{{ $lmWidget['bag_description'] }}</p>
                @if ($lmWidget['bag_cta_label'] !== '' && $lmWidget['bag_cta_url'] !== '')
                    <a class="lm-button" href="{{ $lmWidget['bag_cta_url'] }}">{{ $lmWidget['bag_cta_label'] }} <x-fa-icon :name="$lmWidget['bag_cta_icon']" /></a>
                @endif
            </div>
            <div class="lm-campaign-bag-images" aria-hidden="true">
                @if ($lmWidget['bag_image_back'] !== '')<img class="lm-bag lm-bag--back" src="{{ $lmWidget['bag_image_back'] }}" alt="" width="1000" height="1000" loading="lazy">@endif
                @if ($lmWidget['bag_image_front'] !== '')<img class="lm-bag lm-bag--front" src="{{ $lmWidget['bag_image_front'] }}" alt="" width="1000" height="1000" loading="lazy">@endif
            </div>
        </article>
        <article class="lm-campaign lm-campaign--meli">
            <div class="lm-campaign-copy">
                <p class="lm-eyebrow">{{ $lmWidget['meli_eyebrow'] }}</p>
                <h3>{!! nl2br(e($lmWidget['meli_title']), false) !!}</h3>
                <p>{{ $lmWidget['meli_description'] }}</p>
                @if ($lmWidget['meli_cta_label'] !== '' && $lmWidget['meli_cta_url'] !== '')
                    <a class="lm-button" href="{{ $lmWidget['meli_cta_url'] }}">{{ $lmWidget['meli_cta_label'] }} <x-fa-icon :name="$lmWidget['meli_cta_icon']" /></a>
                @endif
            </div>
            @if ($lmWidget['meli_image'] !== '')<img class="lm-campaign-product lm-meli-product" src="{{ $lmWidget['meli_image'] }}" alt="{{ $lmWidget['meli_image_alt'] }}" width="1000" height="1000" loading="lazy">@endif
        </article>
        <article class="lm-campaign lm-campaign--montessori">
            <div class="lm-campaign-copy">
                <p class="lm-eyebrow">{{ $lmWidget['montessori_eyebrow'] }}</p>
                <h3>{!! nl2br(e($lmWidget['montessori_title']), false) !!}</h3>
                <p>{{ $lmWidget['montessori_description'] }}</p>
                @if ($lmWidget['montessori_cta_label'] !== '' && $lmWidget['montessori_cta_url'] !== '')
                    <a class="lm-button lm-button--green" href="{{ $lmWidget['montessori_cta_url'] }}">{{ $lmWidget['montessori_cta_label'] }} <x-fa-icon :name="$lmWidget['montessori_cta_icon']" /></a>
                @endif
            </div>
            @if ($lmWidget['montessori_image'] !== '')<img class="lm-campaign-product lm-montessori-product" src="{{ $lmWidget['montessori_image'] }}" alt="{{ $lmWidget['montessori_image_alt'] }}" width="1024" height="768" loading="lazy">@endif
        </article>
    </div>
</section>
