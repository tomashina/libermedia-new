@php($fixedWidget = \App\Support\LibermediaWidgetSettings::definition((string) $actionBlock->code) !== [])
<button type="button" wire:click="openPreview({{ $actionBlock->id }})" class="rounded-lg border border-cyan-200 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-50">{{ __('Preview') }}</button>
@if ($canUpdateBlocks)
    <a href="{{ route('admin.content.blocks.edit', ['block' => $actionBlock->id, 'locale' => $locale]) }}" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white">{{ __('Edit') }}</a>
@endif
@if (($fixedWidget && $canUpdateBlocks) || (! $fixedWidget && $canDeleteBlocks))
    <details class="relative">
        <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-300 text-slate-500 hover:bg-slate-50" aria-label="Dodatne akcije: {{ $actionBlock->name }}" title="Dodatne akcije"><span aria-hidden="true">•••</span></summary>
        <div class="absolute right-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
            @if ($fixedWidget)
                <button type="button" wire:click="disableBlock({{ $actionBlock->id }})" wire:confirm="Isključiti blok? Sadržaj će ostati sačuvan." class="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Isključi blok</button>
            @else
                <button type="button" wire:click="delete({{ $actionBlock->id }})" wire:confirm="{{ __('Delete this content block?') }}" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">{{ __('Delete') }}</button>
            @endif
        </div>
    </details>
@endif
