@php($lmWidget = \App\Support\LibermediaWidgetSettings::forBlock($block ?? new \App\Models\Content\ContentBlock(['code' => 'libermedia-home-assurance']), $translation ?? null))
<section class="lm-assurance" aria-labelledby="lm-assurance-title" data-libermedia-assurance>
    <div class="lm-assurance-heading">
        <span class="lm-assurance-seal">@if ($lmWidget['image'] !== '')<img src="{{ $lmWidget['image'] }}" alt="" width="48" height="48" aria-hidden="true">@endif</span>
        <p class="lm-eyebrow">{{ $lmWidget['subtitle'] }}</p>
        <h2 id="lm-assurance-title">{{ $lmWidget['title'] }}</h2>
        <p>{!! \App\Support\LibermediaWidgetSettings::formattedText($lmWidget['body']) !!}</p>
    </div>
    <div class="lm-assurance-points">
        @foreach (range(1, 3) as $lmPoint)
            <div><x-fa-icon :name="$lmWidget['point_'.$lmPoint.'_icon']" style="regular" /><h3>{{ $lmWidget['point_'.$lmPoint.'_title'] }}</h3><p>{{ $lmWidget['point_'.$lmPoint.'_description'] }}</p>@if ($lmWidget['point_'.$lmPoint.'_cta_label'] !== '' && $lmWidget['point_'.$lmPoint.'_cta_url'] !== '')<a href="{{ $lmWidget['point_'.$lmPoint.'_cta_url'] }}">{{ $lmWidget['point_'.$lmPoint.'_cta_label'] }} <x-fa-icon :name="$lmWidget['point_'.$lmPoint.'_cta_icon']" /></a>@endif</div>
        @endforeach
    </div>
</section>
