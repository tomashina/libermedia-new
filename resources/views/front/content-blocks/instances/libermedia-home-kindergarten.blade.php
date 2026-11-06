@php($lmWidget = \App\Support\LibermediaWidgetSettings::forBlock($block ?? new \App\Models\Content\ContentBlock(['code' => 'libermedia-home-kindergarten']), $translation ?? null))
<section class="lm-kindergarten" aria-labelledby="lm-kindergarten-title" data-libermedia-kindergarten>
    @if ($lmWidget['image'] !== '')<img class="lm-kindergarten-photo" src="{{ $lmWidget['image'] }}" alt="{{ $lmWidget['image_alt'] }}" width="750" height="550" loading="lazy">@endif
    <div class="lm-kindergarten-copy">
        <p class="lm-eyebrow">{{ $lmWidget['subtitle'] }}</p>
        <h2 id="lm-kindergarten-title">{!! nl2br(e($lmWidget['title']), false) !!}</h2>
        <p>{{ $lmWidget['description'] }}</p>
        <p>{{ $lmWidget['description_secondary'] }}</p>
        @if ($lmWidget['cta_label'] !== '' && $lmWidget['cta_url'] !== '')
            <a class="lm-button" href="{{ $lmWidget['cta_url'] }}" target="_blank" rel="noopener noreferrer">{{ $lmWidget['cta_label'] }} <x-fa-icon :name="$lmWidget['cta_icon']" /></a>
        @endif
    </div>
    @if ($lmWidget['image_outdoor'] !== '')<img class="lm-kindergarten-photo lm-kindergarten-photo--outdoor" src="{{ $lmWidget['image_outdoor'] }}" alt="{{ $lmWidget['image_outdoor_alt'] }}" width="750" height="550" loading="lazy">@endif
</section>
