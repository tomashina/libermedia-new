<div class="space-y-6">
    @php
        $displayText = static fn ($value): string => html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $imageUrl = static fn ($product): ?string => $product ? \App\Support\Media\LegacyCatalogImage::first($product, ['thumb_100x100', 'card_320w']) : null;
        $productName = static fn ($product) => $product?->translations->firstWhere('locale', app()->getLocale())?->name
            ?? $product?->translations->firstWhere('locale', config('app.fallback_locale'))?->name
            ?? $product?->translations->first()?->name ?? $product?->sku ?? $product?->code ?? 'Nedostupan artikl';
        $visibleDiscounts = $isB2cEditor ? ($defaultDiscountIndex !== null ? [$defaultDiscountIndex => $discounts[$defaultDiscountIndex]] : []) : $discounts;
        $bundleNow = $statusNow;
    @endphp

    @push('page-styles')
        <style>
            @media (max-width: 767px) {
                .bundle-offers-table, .bundle-offers-table tbody { display: block; width: 100%; }
                .bundle-offers-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); }
                .bundle-offers-table tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: .75rem 0; overflow: hidden; border: 1px solid #dbe4ee; border-radius: .85rem; background: #fff; }
                .bundle-offers-table td { display: flex; min-width: 0; flex-direction: column; gap: .25rem; padding: .75rem; border: 0; text-align: left; }
                .bundle-offers-table td[data-label]::before { content: attr(data-label); color: #64748b; font-size: .6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
                .bundle-offers-table td:first-child, .bundle-offers-table td:last-child { grid-column: 1 / -1; }
                .bundle-offers-table td:first-child { background: #f8fafc; }
                .bundle-offers-table td:first-child p { white-space: normal; }
                .bundle-offers-table td[data-label="Upravljanje"] { align-items: flex-end; border-top: 1px solid #e2e8f0; }
                .bundle-offers-table button { min-height: 2.75rem; }
            }
        </style>
    @endpush

    <div class="admin-panel admin-search-panel p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Katalog / Kompleti</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $editing ? ($editingId ? 'Uredi bundle ponudu' : 'Nova bundle ponuda') : 'Bundle ponude' }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $editing ? 'Odaberite artikle, njihove količine i popust koji kupac ostvaruje kupnjom kompleta.' : 'Složite komplete artikala s posebnim popustom i odredite kada su dostupni kupcima.' }}</p>
            </div>
            @if ($editing)
                <button type="button" wire:click="cancelEditing" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                    <x-fa-icon name="arrow-left" class="h-4 w-4" /> Natrag na popis
                </button>
            @elseif ($canCreate)
                <button type="button" wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">
                    <x-fa-icon name="plus" class="h-4 w-4" /> Nova bundle ponuda
                </button>
            @endif
        </div>

        @unless ($editing)
            <div class="mt-6 grid items-end gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_12rem_15rem]">
                <div>
                    <label for="bundle-search" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pretraži ponude</label>
                    <input id="bundle-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Naziv ponude" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="bundle-status" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Status</label>
                    <select id="bundle-status" wire:model.live="status" class="admin-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Svi statusi</option>
                        <option value="active">Aktivne</option>
                        <option value="scheduled">Zakazane</option>
                        <option value="expired">Istekle</option>
                        <option value="disabled">Isključene</option>
                    </select>
                </div>
                <div>
                    <label for="bundle-sort" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Poredaj po</label>
                    <select id="bundle-sort" wire:model.live="sort" class="admin-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="order">Redoslijedu prikaza</option>
                        <option value="newest">Najnovijim ponudama</option>
                        <option value="title">Nazivu ponude</option>
                        <option value="expiry">Najbližem isteku</option>
                    </select>
                </div>
            </div>
            @if (trim($search) !== '' || $status !== '' || $sort !== 'order')
                <button type="button" wire:click="clearFilters" class="mt-3 text-xs font-semibold text-cyan-700 hover:text-cyan-900">Poništi filtre</button>
            @endif
        @endunless
    </div>

    @if (session('bundle_status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('bundle_status') }}</div>
    @endif

    @if ($editing)
        <form wire:submit="save" wire:key="bundle-editor-{{ $editingId ?? 'new' }}" class="space-y-6" x-data x-init="$nextTick(() => $el.scrollIntoView({ block: 'start' }))">
            @if ($errors->any())
                <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Ponuda još nije spremljena. Provjerite označena polja.</div>
            @endif

            <div class="admin-panel admin-form-panel p-6">
                <h2 class="admin-section-title">Osnovni podaci</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label for="bundle-title" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Naziv ponude</label>
                        <input id="bundle-title" type="text" wire:model="title" maxlength="255" required placeholder="Npr. Poklon komplet za male graditelje" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" @error('title') aria-invalid="true" aria-describedby="bundle-title-error" @enderror>
                        @error('title')<p id="bundle-title-error" class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bundle-sort" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Redoslijed</label>
                        <input id="bundle-sort" type="number" min="0" max="999999" wire:model="sortOrder" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Manji broj prikazuje se prije.</p>
                        @error('sortOrder')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="mt-4 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500"> Aktivna ponuda
                </label>
                <p class="mt-1 text-xs text-slate-500">Isključena ponuda se ne prikazuje kupcima i ne obračunava popust.</p>
                @error('isActive')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="bundle-start" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vrijedi od <span class="font-normal normal-case tracking-normal">(neobavezno)</span></label>
                        <input id="bundle-start" type="datetime-local" wire:model="startsAt" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @error('startsAt')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bundle-end" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Vrijedi do <span class="font-normal normal-case tracking-normal">(neobavezno)</span></label>
                        <input id="bundle-end" type="datetime-local" wire:model="endsAt" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @error('endsAt')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Bez datuma ponuda vrijedi dok je uključena.</p>
                @if (\App\Support\StorefrontTheme::usesLibermediaBrand())<p class="mt-1 text-xs text-slate-500">Datumi i vrijeme prikazani su po hrvatskom vremenu.</p>@endif
            </div>

            <div class="admin-panel admin-form-panel p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="admin-section-title">Artikli u kompletu</h2>
                    <span class="admin-chip">Odabrano: {{ count($products) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-600">Dodajte najmanje dva različita artikla. Za svaki odredite broj komada u jednom kompletu.</p>
                <div class="mt-4">
                    <label for="bundle-product-search" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pronađi i dodaj artikl</label>
                    <input id="bundle-product-search" type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Naziv, šifra ili SKU — najmanje 2 znaka" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" autocomplete="off">
                    <p wire:loading wire:target="productSearch" role="status" class="mt-2 text-xs text-slate-500">Tražim artikle…</p>
                    @if (mb_strlen(trim($productSearch)) >= 2)
                        <div class="mt-2 max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                            @forelse ($productResults as $product)
                                <button type="button" wire:key="bundle-result-{{ $product->id }}" wire:click="addProduct({{ $product->id }})" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-left text-sm hover:border-cyan-300 hover:bg-cyan-50">
                                    @if ($thumb = $imageUrl($product))
                                        <img src="{{ $thumb }}" alt="" width="44" height="44" class="h-11 w-11 shrink-0 rounded-lg border border-slate-200 bg-white object-contain" loading="lazy">
                                    @else
                                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400"><x-fa-icon name="image" class="h-4 w-4" /></span>
                                    @endif
                                    <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-800">{{ $displayText($productName($product)) }}</span><span class="mt-1 block text-xs text-slate-500">{{ $product->sku ?: $product->code }}{{ ! $product->is_active ? ' · Neaktivan artikl' : '' }}</span></span>
                                    <span class="inline-flex shrink-0 items-center gap-1 font-semibold text-cyan-700"><x-fa-icon name="plus" class="h-3 w-3" /> Dodaj</span>
                                </button>
                            @empty
                                <p role="status" class="px-3 py-4 text-sm text-slate-600">Nema novih artikala za ovu pretragu. Već odabrani artikli nalaze se ispod.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
                @error('products')<p class="mt-3 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                <div class="mt-4 space-y-3">
                    @forelse ($products as $index => $row)
                        @php
                            $selectedProduct = $selectedProducts->get($row['product_id']);
                        @endphp
                        <div wire:key="bundle-product-{{ $row['product_id'] }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                @if ($thumb = $imageUrl($selectedProduct))
                                    <img src="{{ $thumb }}" alt="" width="56" height="56" class="h-14 w-14 shrink-0 rounded-lg border border-slate-200 bg-white object-contain" loading="lazy">
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-800">{{ $displayText($productName($selectedProduct)) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $selectedProduct?->sku ?: $selectedProduct?->code }}</p>
                                    @error('products.'.$index.'.product_id')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:w-28">
                                    <label for="bundle-product-quantity-{{ $row['product_id'] }}" class="mb-1 block text-xs font-semibold text-slate-600">Količina u kompletu</label>
                                    <input id="bundle-product-quantity-{{ $row['product_id'] }}" type="number" min="1" max="999" step="1" wire:model="products.{{ $index }}.quantity" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    @error('products.'.$index.'.quantity')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <button type="button" wire:click="clearProduct({{ $index }})" aria-label="Ukloni artikl {{ $displayText($productName($selectedProduct)) }} iz kompleta" class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"><x-fa-icon name="xmark" class="h-3 w-3" /> Ukloni</button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-700">Još nema artikala u kompletu.</p>
                            <p class="mt-1 text-sm text-slate-500">Pronađite artikl iznad i odaberite „Dodaj”.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="admin-panel admin-form-panel p-6">
                <h2 class="admin-section-title">{{ $isB2cEditor ? 'Popust na komplet' : 'Popusti po grupi kupaca' }}</h2>
                <p class="mt-2 text-sm text-slate-600">Popust vrijedi kada su svi artikli kompleta u košarici u zadanim količinama. Fiksni iznos je popust na cijeli komplet.</p>
                @error('discounts')<p class="mt-3 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                <div class="mt-4 space-y-3">
                    @forelse ($visibleDiscounts as $index => $discount)
                        <div wire:key="bundle-discount-{{ $index }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="grid items-start gap-3 sm:grid-cols-2 {{ $isB2cEditor ? '' : 'lg:grid-cols-4' }}">
                                @unless ($isB2cEditor)
                                    <div>
                                        <label for="bundle-discount-group-{{ $index }}" class="mb-1 block text-xs font-semibold text-slate-600">Grupa kupaca</label>
                                        <select id="bundle-discount-group-{{ $index }}" wire:model="discounts.{{ $index }}.customer_group_id" class="admin-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                            <option value="">Odaberi grupu</option>
                                            @foreach ($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}{{ ! $group->is_active ? ' (neaktivna)' : '' }}</option>@endforeach
                                        </select>
                                        @error('discounts.'.$index.'.customer_group_id')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                    </div>
                                @endunless
                                <div>
                                    <label for="bundle-discount-type-{{ $index }}" class="mb-1 block text-xs font-semibold text-slate-600">Vrsta popusta</label>
                                    <select id="bundle-discount-type-{{ $index }}" wire:model.live="discounts.{{ $index }}.type" class="admin-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"><option value="percent">Postotak (%)</option><option value="fixed">Fiksni iznos (€)</option></select>
                                    @error('discounts.'.$index.'.type')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="bundle-discount-value-{{ $index }}" class="mb-1 block text-xs font-semibold text-slate-600">{{ ($discount['type'] ?? 'percent') === 'percent' ? 'Popust (%)' : 'Popust na komplet (€)' }}</label>
                                    <input id="bundle-discount-value-{{ $index }}" type="number" min="0" max="{{ ($discount['type'] ?? 'percent') === 'percent' ? 100 : 999999 }}" step="0.01" wire:model="discounts.{{ $index }}.value" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                    @error('discounts.'.$index.'.value')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                    @if ($isB2cEditor)
                                        @error('discounts.'.$index.'.customer_group_id')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                @unless ($isB2cEditor)
                                    <div class="sm:pt-5"><button type="button" wire:click="clearDiscount({{ $index }})" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Ukloni popust</button></div>
                                @endunless
                            </div>
                        </div>
                    @empty
                        <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $isB2cEditor ? 'Za spremanje popusta potrebna je grupa kupaca. Provjerite postavke grupa kupaca.' : 'Dodajte grupu kupaca i odredite popust za komplet.' }}</div>
                    @endforelse
                </div>
                @unless ($isB2cEditor)
                    <button type="button" wire:click="addDiscount" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"><x-fa-icon name="plus" class="h-3 w-3" /> Dodaj grupu kupaca</button>
                @endunless
            </div>

            <div class="admin-form-actions flex flex-wrap items-center justify-end gap-3">
                <button type="button" wire:click="cancelEditing" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Odustani</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-xl bg-cyan-700 px-5 py-2 text-sm font-semibold text-white hover:bg-cyan-800 disabled:opacity-50"><span wire:loading.remove wire:target="save">Spremi ponudu</span><span wire:loading wire:target="save">Spremam…</span></button>
            </div>
        </form>
    @else
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Pregled bundle ponuda">
            @foreach (['total' => 'Sve ponude', 'active' => 'Aktivne', 'scheduled' => 'Zakazane', 'expired' => 'Istekle', 'disabled' => 'Isključene'] as $summaryKey => $summaryLabel)
                <div class="admin-panel px-4 py-3" data-bundle-summary="{{ $summaryKey }}">
                    <p class="text-xs font-medium text-slate-500">{{ $summaryLabel }}</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight {{ $summaryKey === 'active' ? 'text-emerald-700' : 'text-slate-900' }}">{{ number_format($statusCounts[$summaryKey]) }}</p>
                </div>
            @endforeach
        </div>
        <div class="admin-panel admin-panel-soft p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="admin-section-title">Popis ponuda</h2>
                <span class="admin-chip">Ukupno: {{ $bundles->total() }}</span>
            </div>
            <div class="mt-4 overflow-x-auto" wire:loading.class="opacity-50" wire:target="search,status,sort,clearFilters">
                <table class="admin-items-table bundle-offers-table min-w-full text-sm">
                    <thead class="text-slate-600"><tr>
                        <th class="px-3 py-3 text-left font-semibold">Naziv ponude</th>
                        <th class="px-3 py-3 text-center font-semibold">Artikli</th>
                        <th class="px-3 py-3 text-left font-semibold">Popust</th>
                        <th class="px-3 py-3 text-left font-semibold">Status</th>
                        <th class="px-3 py-3 text-left font-semibold">Razdoblje</th>
                        @if ($canUpdate || $canDelete)<th class="px-3 py-3 text-right font-semibold">Upravljanje</th>@endif
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($bundles as $bundle)
                            @php
                                $rowStatus = ! $bundle->is_active ? 'Isključena' : (($bundle->ends_at && $bundle->ends_at->lt($bundleNow)) ? 'Istekla' : (($bundle->starts_at && $bundle->starts_at->gt($bundleNow)) ? 'Zakazana' : 'Aktivna'));
                                $statusClass = match ($rowStatus) {
                                    'Aktivna' => 'bg-emerald-100 text-emerald-800',
                                    'Zakazana' => 'bg-cyan-50 text-cyan-800',
                                    'Istekla' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <tr wire:key="bundle-row-{{ $bundle->id }}">
                                <td class="px-3 py-4 text-slate-800">
                                    @if ($canUpdate)
                                        <button type="button" wire:click="edit({{ $bundle->id }})" class="text-left font-semibold hover:text-cyan-700">{{ $displayText($bundle->title) }}</button>
                                    @else
                                        <p class="font-semibold">{{ $displayText($bundle->title) }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex shrink-0 gap-1" aria-hidden="true">
                                            @foreach ($bundle->products->take(3) as $bundleProduct)
                                                @if ($thumb = $imageUrl($bundleProduct))
                                                    <img src="{{ $thumb }}" alt="" width="32" height="32" class="h-8 w-8 rounded-md border border-slate-200 bg-white object-contain" loading="lazy">
                                                @endif
                                            @endforeach
                                        </div>
                                        <p class="max-w-lg truncate text-xs text-slate-500">{{ $displayText($bundle->products->take(3)->map($productName)->join(' + ')) }}{{ $bundle->products_count > 3 ? ' + '.($bundle->products_count - 3).' više' : '' }}</p>
                                    </div>
                                </td>
                                <td data-label="Artikli" class="px-3 py-3 text-center text-slate-700"><span class="font-semibold">{{ $bundle->products_count }}</span><span class="mt-1 block whitespace-nowrap text-xs text-slate-500">{{ (int) $bundle->products->sum('pivot.quantity') }} kom.</span></td>
                                <td data-label="Popust" class="px-3 py-3 text-xs text-slate-700">
                                    @php($displayDiscounts = $isB2cEditor ? $bundle->discounts->where('customer_group_id', $defaultDiscountGroupId) : $bundle->discounts)
                                    @forelse ($displayDiscounts as $bundleDiscount)
                                        <p class="whitespace-nowrap font-semibold">{{ $bundleDiscount->type === 'percent' ? rtrim(rtrim(number_format((float) $bundleDiscount->value, 2, ',', '.'), '0'), ',').' %' : number_format((float) $bundleDiscount->value, 2, ',', '.').' €' }}</p>
                                        @unless ($isB2cEditor)<p class="text-slate-500">{{ $bundleDiscount->group?->name ?? 'Grupa kupaca' }}</p>@endunless
                                    @empty
                                        <span class="text-amber-700">Nije postavljen</span>
                                    @endforelse
                                </td>
                                <td data-label="Status" class="px-3 py-3"><span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $rowStatus }}</span></td>
                                <td data-label="Razdoblje" class="px-3 py-3 text-xs text-slate-600">
                                    @if ($bundle->starts_at)<p>Od {{ $bundle->starts_at->copy()->timezone($displayTimezone)->format('d.m.Y. H:i') }}</p>@endif
                                    <p>{{ $bundle->ends_at ? 'Do '.$bundle->ends_at->copy()->timezone($displayTimezone)->format('d.m.Y. H:i') : 'Bez ograničenja' }}</p>
                                </td>
                                @if ($canUpdate || $canDelete)
                                    <td data-label="Upravljanje" class="px-3 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @if ($canUpdate)<button type="button" wire:click="edit({{ $bundle->id }})" aria-label="Uredi ponudu {{ $displayText($bundle->title) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Uredi</button>@endif
                                            @if ($canDelete)<button type="button" wire:click="delete({{ $bundle->id }})" wire:confirm="Obrisati ponudu „{{ $displayText($bundle->title) }}”?" aria-label="Obriši ponudu {{ $displayText($bundle->title) }}" class="rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Obriši</button>@endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ ($canUpdate || $canDelete) ? 6 : 5 }}" class="px-3 py-12 text-center">
                                <p class="font-semibold text-slate-700">{{ trim($search) !== '' || $status !== '' ? 'Nema ponuda za odabrane filtre.' : 'Još nema bundle ponuda.' }}</p>
                                @if (trim($search) !== '' || $status !== '')
                                    <button type="button" wire:click="clearFilters" class="mt-2 text-sm font-semibold text-cyan-700 hover:text-cyan-900">Prikaži sve ponude</button>
                                @elseif ($canCreate)
                                    <button type="button" wire:click="create" class="mt-2 text-sm font-semibold text-cyan-700 hover:text-cyan-900">Kreiraj prvu ponudu →</button>
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($bundles->hasPages())<div class="mt-4">{{ $bundles->links() }}</div>@endif
        </div>
    @endif
</div>
