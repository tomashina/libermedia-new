<?php

namespace App\Livewire\Admin\Content\Block;

use App\Models\Content\ContentBlock;
use App\Models\Content\ContentBlockSlot;
use App\Services\Content\ContentBlockResolver;
use App\Services\Settings\SystemSettingsService;
use App\Support\LibermediaWidgetSettings;
use App\Support\StorefrontTheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const PAGE_NAME = 'contentBlocksPage';

    private const HOME_PLACEMENTS = [
        'home.hero' => 'Uvodni banneri',
        'home.categories' => 'Kategorije',
        'home.before_products' => 'Izdvojena ponuda',
        'home.after_products' => 'Dodatna ponuda',
        'home.bottom' => 'Donji dio početne',
        'home.hero_benefits' => 'Prednosti kupnje',
    ];

    public string $search = '';

    public string $surface = 'all';

    public ?int $previewBlockId = null;

    public string $locale;

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->surface = 'all';
        $this->locale = (string) (request()->query('locale') ?: config('app.locale'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: self::PAGE_NAME);
    }

    public function updatedSurface(): void
    {
        $this->resetPage(pageName: self::PAGE_NAME);
    }

    public function openPreview(int $id): void
    {
        $this->authorizeAccess();
        $exists = ContentBlock::query()->whereKey($id)->exists();
        $this->previewBlockId = $exists ? $id : null;
    }

    public function closePreview(): void
    {
        $this->previewBlockId = null;
    }

    public function moveHomeWidget(int $slotId, string $direction): void
    {
        $this->authorizeAccess('content.blocks.update');
        abort_unless(StorefrontTheme::usesLibermediaBrand(), 404);
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $changed = DB::transaction(function () use ($slotId, $direction): bool {
            $slot = $this->managedSlotQuery()->with('block')->find($slotId);
            abort_unless($slot, 404);
            $slots = $this->managedSlotQuery()->where('placement', $slot->placement)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $position = $slots->search(fn ($row) => (int) $row->id === $slotId);
            $next = $position + ($direction === 'up' ? -1 : 1);
            if ($position === false || $next < 0 || $next >= $slots->count()) {
                return false;
            }

            $ordered = $slots->all();
            [$ordered[$position], $ordered[$next]] = [$ordered[$next], $ordered[$position]];
            foreach ($ordered as $index => $orderedSlot) {
                $orderedSlot->update(['sort_order' => ($index + 1) * 10, 'updated_by' => auth()->id()]);
            }
            $this->logWidgetChange($slot->block, 'Homepage block order changed.', [
                'slot_id' => $slotId, 'placement' => $slot->placement, 'direction' => $direction,
            ]);

            return true;
        });
        if ($changed) {
            ContentBlockResolver::bumpCacheVersion();
        }
    }

    public function toggleHomeWidget(int $slotId): void
    {
        $this->authorizeAccess('content.blocks.update');
        abort_unless(StorefrontTheme::usesLibermediaBrand(), 404);
        DB::transaction(function () use ($slotId): void {
            $slot = $this->managedSlotQuery()->with('block')->lockForUpdate()->find($slotId);
            abort_unless($slot, 404);
            $active = ! ($slot->is_active && $slot->block->is_active);
            $slot->update(['is_active' => $active, 'updated_by' => auth()->id()]);
            // Turning off one placement must preserve uses of the same block elsewhere.
            if ($active && ! $slot->block->is_active) {
                $slot->block->update(['is_active' => true, 'updated_by' => auth()->id()]);
            }
            $this->logWidgetChange($slot->block, 'Homepage block visibility changed.', [
                'slot_id' => $slotId, 'placement' => $slot->placement, 'is_active' => $active,
            ]);
        });
        ContentBlockResolver::bumpCacheVersion();
    }

    public function disableBlock(int $id): void
    {
        $this->authorizeAccess('content.blocks.update');
        abort_unless(StorefrontTheme::usesLibermediaBrand(), 404);
        $block = ContentBlock::query()->findOrFail($id);
        abort_unless($this->isFixedWidget($block), 404);
        DB::transaction(function () use ($block): void {
            $block->update(['is_active' => false, 'updated_by' => auth()->id()]);
            $block->slots()->update(['is_active' => false, 'updated_by' => auth()->id()]);
            $this->logWidgetChange($block, 'Fixed homepage block disabled.', ['is_active' => false]);
        });
        ContentBlockResolver::bumpCacheVersion();
        $this->dispatch('notify', type: 'success', message: __('Blok je isključen. Sadržaj je sačuvan.'));
    }

    public function delete(int $id): void
    {
        $block = ContentBlock::query()->findOrFail($id);
        if (StorefrontTheme::usesLibermediaBrand() && $this->isFixedWidget($block)) {
            $this->disableBlock($id);

            return;
        }
        $this->authorizeAccess('content.blocks.delete');
        $code = (string) $block->code;
        activity('content_blocks')->performedOn($block)->causedBy(auth()->user())
            ->event('deleted')->withProperties(['code' => $code])->log('Content block deleted');
        $block->delete();
        if (! StorefrontTheme::usesLibermediaBrand()) {
            $this->deleteTemplateFile($code);
        }
        ContentBlockResolver::bumpCacheVersion();
        $this->dispatch('notify', type: 'success', message: __('Content block deleted.'));
    }

    public function render()
    {
        $this->authorizeAccess();
        $libermediaManager = StorefrontTheme::usesLibermediaBrand();
        $perPage = app(SystemSettingsService::class)->getInt(
            'admin_items_per_page', (int) config('admin_ui.pagination.admin_items_per_page', 20), 5, 200
        );
        $rows = ContentBlock::query()->withCount(['slots', 'items'])
            ->with([
                'translations' => fn ($q) => $q->where('locale', $this->locale),
                'slots' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])
            ->when($libermediaManager, fn (Builder $query) => $query->where(fn (Builder $block) => $block
                ->whereDoesntHave('slots')->orWhereHas('slots', fn (Builder $slot) => $this->otherSlotQuery($slot))))
            ->when($this->search !== '', fn (Builder $query) => $this->searchBlocks($query))
            ->when(in_array($this->surface, ['desktop', 'mobile'], true), function (Builder $query) use ($libermediaManager): void {
                $query->whereHas('slots', function (Builder $slot) use ($libermediaManager): void {
                    if ($libermediaManager) {
                        $this->otherSlotQuery($slot);
                    }
                    $this->surfaceFilter($slot);
                });
            })
            ->orderByDesc('id')->paginate($perPage, ['*'], self::PAGE_NAME);

        $allSlots = $libermediaManager ? $this->managedSlotQuery()->with('block.translations')
            ->orderBy('sort_order')->orderBy('id')->get() : collect();
        $visibleSlots = $allSlots;
        if ($libermediaManager && ($this->search !== '' || in_array($this->surface, ['desktop', 'mobile'], true))) {
            $visibleSlots = $this->managedSlotQuery()->with('block.translations')
                ->when($this->search !== '', fn (Builder $query) => $query->whereHas('block', fn (Builder $block) => $this->searchBlocks($block)))
                ->when(in_array($this->surface, ['desktop', 'mobile'], true), fn (Builder $query) => $this->surfaceFilter($query))
                ->orderBy('sort_order')->orderBy('id')->get();
        }
        $homeWidgetPositions = [];
        $homeWidgetCounts = [];
        foreach ($allSlots->groupBy('placement') as $placementSlots) {
            foreach ($placementSlots->values() as $index => $slot) {
                $homeWidgetPositions[$slot->id] = $index + 1;
                $homeWidgetCounts[$slot->id] = $placementSlots->count();
            }
        }
        $homeWidgetGroups = collect(self::HOME_PLACEMENTS)->except('home.hero_benefits')
            ->map(fn (string $label, string $placement): array => [
                'placement' => $placement, 'label' => $label,
                'slots' => $visibleSlots->where('placement', $placement)->values(),
            ])->filter(fn (array $group): bool => $group['slots']->isNotEmpty())->values();

        $previewBlock = $this->previewBlockId ? ContentBlock::query()->withCount(['slots', 'items'])
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', [$this->locale, config('app.locale')]),
                'slots' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            ])->find($this->previewBlockId) : null;

        return view('livewire.admin.content.block.index', [
            'rows' => $rows, 'perPage' => $perPage, 'previewBlock' => $previewBlock,
            'libermediaManager' => $libermediaManager, 'homeWidgetGroups' => $homeWidgetGroups,
            'footerWidgetSlots' => $visibleSlots->where('placement', 'home.hero_benefits')->values(),
            'homeWidgetPositions' => $homeWidgetPositions, 'homeWidgetCounts' => $homeWidgetCounts,
            'canCreateBlocks' => $this->canAccess('content.blocks.create'),
            'canUpdateBlocks' => $this->canAccess('content.blocks.update'),
            'canDeleteBlocks' => $this->canAccess('content.blocks.delete'),
            'managedPlacements' => self::HOME_PLACEMENTS,
        ]);
    }

    private function managedSlotQuery(): Builder
    {
        return ContentBlockSlot::query()->whereIn('placement', array_keys(self::HOME_PLACEMENTS))
            ->where(fn (Builder $query) => $query->whereNull('target_type')->orWhere('target_type', ''))
            ->where(fn (Builder $query) => $query->whereNull('target_ref')->orWhere('target_ref', ''))
            ->whereHas('block');
    }

    private function otherSlotQuery(Builder $query): Builder
    {
        return $query->where(fn (Builder $slot) => $slot
            ->whereNotIn('placement', array_keys(self::HOME_PLACEMENTS))
            ->orWhere(fn (Builder $target) => $target->whereNotNull('target_type')->where('target_type', '!=', ''))
            ->orWhere(fn (Builder $target) => $target->whereNotNull('target_ref')->where('target_ref', '!=', '')));
    }

    private function surfaceFilter(Builder $query): Builder
    {
        return $query->where(fn (Builder $slot) => $slot->whereNull('frontend_variant')
            ->orWhere('frontend_variant', 'all')->orWhere('frontend_variant', $this->surface));
    }

    private function searchBlocks(Builder $query): Builder
    {
        return $query->where(fn (Builder $block) => $block->where('name', 'like', '%'.$this->search.'%')
            ->orWhere('code', 'like', '%'.$this->search.'%')->orWhere('type', 'like', '%'.$this->search.'%')
            ->orWhereHas('translations', fn (Builder $translation) => $translation->where('title', 'like', '%'.$this->search.'%')));
    }

    private function isFixedWidget(ContentBlock $block): bool
    {
        return LibermediaWidgetSettings::definition((string) $block->code) !== [];
    }

    private function logWidgetChange(ContentBlock $block, string $message, array $properties): void
    {
        activity('content_blocks')->performedOn($block)->causedBy(auth()->user())
            ->event('updated')->withProperties(['code' => $block->code] + $properties)->log($message);
    }

    private function canAccess(string $ability): bool
    {
        $user = auth()->user();

        return $user && ($user->isA('superadmin') || ($user->can('admin.access') && $user->can($ability)));
    }

    private function authorizeAccess(string $ability = 'content.blocks.view'): void
    {
        abort_unless($this->canAccess($ability), 403);
    }

    private function deleteTemplateFile(string $code): void
    {
        $path = resource_path('views/front/content-blocks/instances/'.$code.'.blade.php');
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
