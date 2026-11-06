<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ $libermediaManager ? 'Widgeti i blokovi' : __('Content Blocks') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $libermediaManager ? 'Uredite sadržaj, redoslijed i vidljivost blokova početne stranice.' : __('Unified builder: block, primary slot, and selected items.') }}</p>
                @if (! $libermediaManager || $rows->total() > 0)
                    <p class="mt-2 text-xs text-slate-500">{{ __('Items per page') }}: <span class="admin-chip">{{ $perPage }}</span></p>
                @endif
            </div>
            <div class="flex w-full gap-2 sm:w-auto sm:items-end">
                <div class="w-full sm:w-80">
                    <label for="content-block-search" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Search') }}</label>
                    <input
                        id="content-block-search"
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $libermediaManager ? 'Pretraži blokove...' : __('Code, name or type...') }}"
                        class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm"
                    />
                </div>
                <div class="w-full sm:w-44">
                    <label for="content-block-surface" class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Surface') }}</label>
                    <select id="content-block-surface" wire:model.live="surface" data-tom-select data-tom-no-search="1" class="admin-select w-full rounded-xl border px-3 py-2 text-sm">
                        <option value="all">{{ __('All') }}</option>
                        <option value="desktop">{{ __('Desktop') }}</option>
                        <option value="mobile">{{ __('Mobile') }}</option>
                    </select>
                </div>
                @if ($canCreateBlocks)
                <a href="{{ route('admin.content.blocks.create') }}" class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-semibold {{ $libermediaManager ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'bg-cyan-700 text-white hover:bg-cyan-800' }}">{{ __('Create') }}</a>
                @endif
            </div>
        </div>
    </div>

    @if ($libermediaManager)
        @php
            $widgetSections = [
                ['heading' => 'Početna — raspored blokova', 'key' => 'home', 'groups' => $homeWidgetGroups],
                ['heading' => 'Podnožje — blokovi', 'key' => 'footer', 'groups' => collect([
                    ['label' => 'Prednosti kupnje', 'slots' => $footerWidgetSlots],
                ])->filter(fn ($group) => $group['slots']->isNotEmpty())],
            ];
        @endphp
        @foreach ($widgetSections as $widgetSection)
            @if ($widgetSection['groups']->isNotEmpty())
                <section class="admin-panel admin-form-panel p-5" data-libermedia-widget-manager="{{ $widgetSection['key'] }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $widgetSection['heading'] }}</h2>
                            <p class="mt-1 text-sm text-slate-600">Uključite blok ili otvorite uređivanje. Strelice mijenjaju redoslijed unutar iste pozicije.</p>
                        </div>
                        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="rounded-lg border border-cyan-200 px-3 py-2 text-sm font-semibold text-cyan-800">Otvori početnu ↗</a>
                    </div>
                    @foreach ($widgetSection['groups'] as $widgetGroup)
                        <div class="mt-5">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $widgetGroup['label'] }}</h3>
                            <ol class="divide-y divide-slate-200 rounded-xl border border-slate-200">
                                @foreach ($widgetGroup['slots'] as $widgetSlot)
                                    @php
                                        $widgetActive = $widgetSlot->is_active && $widgetSlot->block->is_active;
                                        $widgetPosition = $homeWidgetPositions[$widgetSlot->id];
                                        $widgetDefinition = \App\Support\LibermediaWidgetSettings::definition((string) $widgetSlot->block->code);
                                        $widgetName = $widgetDefinition['label'] ?? $widgetSlot->block->name;
                                    @endphp
                                    <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="libermedia-widget-{{ $widgetSlot->id }}">
                                        <span class="w-6 text-sm font-semibold text-slate-400">{{ $widgetPosition }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-slate-900">{{ $widgetName }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                @if (trim((string) $widgetSlot->block->name) !== trim((string) $widgetName))
                                                    {{ $widgetSlot->block->name }} <span aria-hidden="true">·</span>
                                                @endif
                                                {{ in_array($widgetSlot->frontend_variant, ['desktop', 'mobile'], true) ? ($widgetSlot->frontend_variant === 'desktop' ? 'Računalo' : 'Mobitel') : 'Svi uređaji' }}
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($canUpdateBlocks)
                                                <button type="button" wire:click="moveHomeWidget({{ $widgetSlot->id }}, 'up')" @disabled($widgetPosition === 1) aria-label="Pomakni gore: {{ $widgetName }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-30">↑</button>
                                                <button type="button" wire:click="moveHomeWidget({{ $widgetSlot->id }}, 'down')" @disabled($widgetPosition === $homeWidgetCounts[$widgetSlot->id]) aria-label="Pomakni dolje: {{ $widgetName }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-30">↓</button>
                                                <button type="button" wire:click="toggleHomeWidget({{ $widgetSlot->id }})" aria-label="Promijeni vidljivost: {{ $widgetName }}" aria-pressed="{{ $widgetActive ? 'true' : 'false' }}" class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $widgetActive ? 'border-cyan-200 bg-cyan-50 text-cyan-800' : 'border-slate-300 bg-slate-100 text-slate-500' }}">{{ $widgetActive ? 'Uključen' : 'Isključen' }}</button>
                                            @else
                                                <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600">{{ $widgetActive ? 'Uključen' : 'Isključen' }}</span>
                                            @endif
                                            @include('livewire.admin.content.block.partials.index-actions', ['actionBlock' => $widgetSlot->block])
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </section>
            @endif
        @endforeach
    @endif

    @if (! $libermediaManager || $rows->total() > 0)
    <div class="admin-panel admin-panel-soft p-5" @if ($libermediaManager) data-libermedia-other-blocks @endif>
        <h2 class="admin-section-title">{{ $libermediaManager ? 'Ostali blokovi' : __('Items') }}</h2>
        @if ($libermediaManager)<p class="mt-1 text-sm text-slate-600">Blokovi bez pozicije na početnoj ili s dodatnim prikazom na drugim stranicama.</p>@endif

        <div class="mt-4 overflow-x-auto">
            <table class="admin-items-table min-w-full text-sm">
                <thead class="text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Code') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Name') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Type') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Placement') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Surface') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Preview') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Items') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Slots') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('State') }}</th>
                        <th class="px-3 py-2 text-right font-semibold">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $title = $row->translations->first()?->title;
                            $otherSlots = $libermediaManager ? $row->slots->filter(fn ($slot) => ! array_key_exists($slot->placement, $managedPlacements)
                                || trim((string) $slot->target_type) !== '' || trim((string) $slot->target_ref) !== '') : $row->slots;
                            $primarySlot = $otherSlots->first(fn ($slot) => $surface === 'all' || in_array($slot->frontend_variant, [null, 'all', $surface], true)) ?? $otherSlots->first();
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $row->code }}</td>
                            <td class="px-3 py-2 text-slate-800">
                                <div class="font-medium">{{ $row->name }}</div>
                                @if ($title)
                                    <div class="text-xs text-slate-500">{{ $title }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ config('content_blocks.types.'.$row->type, $row->type) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">
                                <div>{{ $primarySlot?->placement ?: __('n/a') }}</div>
                                @if (!empty($primarySlot?->target_type))
                                    <div class="mt-1 text-slate-500">{{ $primarySlot->target_type }}: {{ $primarySlot->target_ref ?: '*' }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-700">
                                @php
                                    $rowSurface = (string) ($primarySlot?->frontend_variant ?? 'all');
                                @endphp
                                @if ($rowSurface === 'desktop')
                                    {{ __('Desktop') }}
                                @elseif ($rowSurface === 'mobile')
                                    {{ __('Mobile') }}
                                @else
                                    {{ __('All') }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @include('admin.content.partials.block-type-preview', ['type' => $row->type, 'size' => 'xs'])
                            </td>
                            <td class="px-3 py-2 text-center text-slate-700">{{ $row->items_count }}</td>
                            <td class="px-3 py-2 text-center text-slate-700">{{ $row->slots_count }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $row->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @if ($libermediaManager)
                                        @include('livewire.admin.content.block.partials.index-actions', ['actionBlock' => $row])
                                    @else
                                    <button type="button" wire:click="openPreview({{ $row->id }})" class="rounded-lg border border-cyan-200 px-2 py-1 text-xs font-semibold text-cyan-800 hover:bg-cyan-50">{{ __('Preview') }}</button>
                                    @if ($canUpdateBlocks)
                                    <a href="{{ route('admin.content.blocks.edit', ['block' => $row->id]) }}" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Edit') }}</a>
                                    @endif
                                    @if ($canDeleteBlocks)
                                    <button type="button" wire:click="delete({{ $row->id }})" wire:confirm="{{ __('Delete this content block?') }}" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ __('Delete') }}</button>
                                    @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('No content blocks yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>

    @endif
    @if ($libermediaManager && $rows->total() === 0 && $homeWidgetGroups->isEmpty() && $footerWidgetSlots->isEmpty())
        <div class="admin-panel p-6 text-sm text-slate-500" role="status">{{ $search !== '' || $surface !== 'all' ? 'Nema blokova za odabrane filtre.' : __('No content blocks yet.') }}</div>
    @endif

    @if ($previewBlock)
        @php
            $previewTranslation = $previewBlock->translations->firstWhere('locale', $locale)
                ?? $previewBlock->translations->firstWhere('locale', config('app.locale'));
            $previewPlacement = (string) ($previewBlock->slots->first()?->placement ?? 'home.hero');
            $previewVariant = (string) ($previewBlock->slots->first()?->frontend_variant ?? 'all');
            $frontVariant = in_array($previewVariant, ['desktop', 'mobile'], true) ? $previewVariant : 'desktop';
            $frontPreviewUrl = route('home', [
                'preview_block' => $previewBlock->id,
                'preview_placement' => $previewPlacement,
                'frontend_variant' => $frontVariant,
            ]);
        @endphp
        <div wire:click="closePreview" class="fixed inset-0 z-[72] bg-slate-900/45 p-4 md:p-6">
            <div wire:click.stop class="mx-auto flex h-full max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-cyan-50 px-5 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Block Preview') }}</p>
                        <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-900">{{ $previewBlock->name }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ __('Code:') }} <span class="font-mono">{{ $previewBlock->code }}</span>
                            <span class="mx-1.5">|</span>
                            {{ __('Type:') }} {{ config('content_blocks.types.'.$previewBlock->type, $previewBlock->type) }}
                            <span class="mx-1.5">|</span>
                            {{ __('Slots:') }} {{ $previewBlock->slots_count }}
                        </p>
                    </div>
                    <div class="inline-flex items-center gap-2">
                        <a href="{{ $frontPreviewUrl }}" target="_blank" rel="noopener" class="rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">
                            {{ __('Open Front') }}
                        </a>
                        <button type="button" wire:click="closePreview" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">{{ __('Close') }}</button>
                    </div>
                </div>

                <div class="grid flex-1 gap-4 overflow-y-auto p-5 lg:grid-cols-[18rem_1fr]">
                    <div class="space-y-3">
                        @include('admin.content.partials.block-type-preview', ['type' => $previewBlock->type, 'size' => 'md'])
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                            <p>
                                {{ __('State:') }}
                                <span class="font-semibold {{ $previewBlock->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                                    {{ $previewBlock->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </p>
                            <p class="mt-1">
                                {{ __('Locale:') }}
                                <span class="font-semibold text-slate-700">{{ $previewTranslation?->locale ?? __('n/a') }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ __('Content Snapshot') }}</p>
                            <h4 class="mt-2 text-base font-semibold text-slate-900">{{ $previewTranslation?->title ?: __('(no title)') }}</h4>
                            @if (!empty($previewTranslation?->subtitle))
                                <p class="mt-1 text-sm text-slate-600">{{ $previewTranslation->subtitle }}</p>
                            @endif
                            @if (!empty($previewTranslation?->cta_label) || !empty($previewTranslation?->cta_url))
                                <p class="mt-2 text-xs text-slate-500">{{ __('CTA:') }} {{ $previewTranslation?->cta_label ?: '-' }} {{ !empty($previewTranslation?->cta_url) ? '-> '.$previewTranslation->cta_url : '' }}</p>
                            @endif
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-600">
                            <p>{{ __('Placement:') }} <span class="font-semibold text-slate-800">{{ $previewPlacement }}</span></p>
                            <p class="mt-1">{{ __('Surface:') }} <span class="font-semibold text-slate-800">{{ $frontVariant }}</span></p>
                            <p class="mt-1">{{ __('Selected items:') }} <span class="font-semibold text-slate-800">{{ $previewBlock->items_count }}</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
