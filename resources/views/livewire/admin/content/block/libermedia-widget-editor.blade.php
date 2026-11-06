@php
    $widgetContentFields = (array) ($this->libermediaDefinition['content_fields'] ?? []);
    $contentLabels = ['title' => 'Naslov', 'subtitle' => 'Nadnaslov', 'cta_label' => 'Tekst gumba', 'cta_url' => 'Poveznica gumba'];
    $widgetGroups = collect($this->libermediaFields)->groupBy(fn ($field) => ($field['group'] ?? '') ?: 'Sadržaj widgeta', preserveKeys: true);
@endphp

<div class="admin-panel admin-form-panel p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="w-full sm:w-48">
            <label for="libermedia-widget-locale" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('admin.common.locale') }}</label>
            <select id="libermedia-widget-locale" wire:model.live="form.locale" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                @foreach ($adminLocaleOptions as $localeOption)
                    <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                @endforeach
            </select>
            @error('form.locale') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-700">
            <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
            {{ __('Widget je uključen') }}
        </label>
    </div>
    @if ($widgetContentFields !== [])
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            @foreach ($widgetContentFields as $fieldKey)
                @if (isset($contentLabels[$fieldKey]))
                    <div>
                        <label for="libermedia-content-{{ $fieldKey }}" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($contentLabels[$fieldKey]) }}</label>
                        @if ($fieldKey === 'title')
                            <textarea id="libermedia-content-{{ $fieldKey }}" rows="2" wire:model="form.{{ $fieldKey }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @else
                            <input id="libermedia-content-{{ $fieldKey }}" type="text" wire:model="form.{{ $fieldKey }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @endif
                        @error('form.'.$fieldKey) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>

@foreach ($widgetGroups as $groupLabel => $groupFields)
    <div class="admin-panel admin-form-panel p-6">
        <p class="admin-section-title">{{ __($groupLabel) }}</p>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            @foreach ($groupFields as $fieldKey => $field)
                <div wire:key="libermedia-widget-field-{{ $fieldKey }}" class="{{ ($field['type'] ?? '') === 'textarea' ? 'md:col-span-2' : '' }}">
                    <label for="libermedia-field-{{ $fieldKey }}" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($field['label']) }}</label>
                    @if (($field['type'] ?? '') === 'checkbox')
                        <input id="libermedia-field-{{ $fieldKey }}" type="checkbox" wire:model="form.libermedia.{{ $fieldKey }}" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                    @elseif (($field['type'] ?? '') === 'textarea')
                        <textarea id="libermedia-field-{{ $fieldKey }}" rows="4" wire:model="form.libermedia.{{ $fieldKey }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @elseif (($field['type'] ?? '') === 'image')
                        <input id="libermedia-field-{{ $fieldKey }}" type="file" accept="image/jpeg,image/png,image/webp" wire:model="libermediaImageUploads.{{ $fieldKey }}" class="block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-cyan-800">
                        <p class="mt-1 text-xs text-slate-500">{{ __('JPG, PNG ili WebP, do 5 MB.') }}</p>
                        <p wire:loading wire:target="libermediaImageUploads.{{ $fieldKey }}" class="mt-1 text-xs text-cyan-700">{{ __('Slika se učitava…') }}</p>
                        @error('libermediaImageUploads.'.$fieldKey) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @php
                            $pendingImage = $libermediaImageUploads[$fieldKey] ?? null;
                            $previewImage = $pendingImage instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                                && $pendingImage->isPreviewable()
                                && in_array($pendingImage->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)
                                && ! $errors->has('libermediaImageUploads.'.$fieldKey)
                                ? $pendingImage->temporaryUrl()
                                : \App\Support\LibermediaWidgetSettings::safeUrl($form['libermedia'][$fieldKey] ?? '', true);
                        @endphp
                        @if ($previewImage !== '')
                            <img src="{{ $previewImage }}" alt="{{ __('Pregled odabrane slike') }}" width="200" height="140" class="mt-3 h-32 max-w-full rounded-lg border border-slate-200 bg-white object-contain" loading="lazy">
                            <button type="button" wire:click="clearLibermediaImage('{{ $fieldKey }}')" class="mt-2 text-xs font-semibold text-rose-700 hover:text-rose-900">{{ __('Ukloni sliku') }}</button>
                        @endif
                        <details class="mt-3 text-xs text-slate-500">
                            <summary class="cursor-pointer">{{ __('Ili upotrijebite postojeću poveznicu slike') }}</summary>
                            <input type="text" aria-label="{{ __($field['label']) }} — {{ __('poveznica') }}" wire:model.live.debounce.500ms="form.libermedia.{{ $fieldKey }}" placeholder="/image/catalog/... ili https://..." class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </details>
                    @else
                        <input id="libermedia-field-{{ $fieldKey }}" type="text" @if (($field['type'] ?? '') === 'icon') list="libermedia-widget-icons" @endif wire:model="form.libermedia.{{ $fieldKey }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @endif
                    @if (! empty($field['help']))
                        <p class="mt-2 text-xs text-slate-500">{{ __($field['help']) }}</p>
                    @endif
                    @error('form.libermedia.'.$fieldKey) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </div>
@endforeach

<datalist id="libermedia-widget-icons">
    @foreach (['credit-card' => 'Plaćanje', 'shield-check' => 'Zaštita', 'heart' => 'Srce', 'truck-fast' => 'Dostava', 'badge-check' => 'Provjereno', 'arrow-right' => 'Strelica desno', 'arrow-up-right' => 'Dijagonalna strelica'] as $icon => $label)
        <option value="{{ $icon }}">{{ __($label) }}</option>
    @endforeach
</datalist>

@if ($this->isItemBlock)
    <div class="admin-panel admin-form-panel p-6">
        <label for="libermedia-widget-items-limit" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Broj prikazanih artikala') }}</label>
        <input id="libermedia-widget-items-limit" type="number" min="1" max="50" wire:model="form.items_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm sm:max-w-48">
        <p class="mt-2 text-xs text-slate-500">{{ __('Artikle odaberite i poredajte u popisu ispod.') }}</p>
        @error('form.items_limit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>
@endif

<details class="admin-panel admin-form-panel p-6">
    <summary class="cursor-pointer text-sm font-semibold text-slate-700">{{ __('Napredne postavke prikaza') }}</summary>
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label for="libermedia-widget-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Naziv widgeta u administraciji') }}</label>
            <input id="libermedia-widget-name" type="text" wire:model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @error('form.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="libermedia-widget-placement" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Mjesto prikaza') }}</label>
            <select id="libermedia-widget-placement" wire:model="form.slot_placement" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                @foreach ($placements as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>
            @error('form.slot_placement') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="libermedia-widget-surface" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Uređaji') }}</label>
            <select id="libermedia-widget-surface" wire:model="form.slot_frontend_variant" class="admin-select w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                @foreach ($frontendVariants as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.slot_frontend_variant') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="libermedia-widget-sort" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Redoslijed prikaza') }}</label>
            <input id="libermedia-widget-sort" type="number" min="0" wire:model="form.slot_sort_order" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @error('form.slot_sort_order') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
        @foreach (['slot_starts_at' => 'Prikaz od', 'slot_ends_at' => 'Prikaz do'] as $fieldKey => $label)
            <div>
                <label for="libermedia-widget-{{ $fieldKey }}" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __($label) }}</label>
                <input id="libermedia-widget-{{ $fieldKey }}" type="datetime-local" wire:model="form.{{ $fieldKey }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                @error('form.'.$fieldKey) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="form.slot_is_active" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
            {{ __('Prikaz na odabranom mjestu je uključen') }}
        </label>
    </div>
</details>
