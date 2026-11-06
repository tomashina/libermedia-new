<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Content\Block\Index;
use App\Models\Content\ContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LibermediaBlockIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia', 'app.locale' => 'hr']);
        app()->setLocale('hr');
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);
        $this->actingAs($admin);
    }

    public function test_home_and_footer_have_simple_lists_without_an_empty_other_panel(): void
    {
        $home = $this->block('libermedia-home-campaigns', [['home.before_products', 'all', 10]]);
        $footer = $this->block('libermedia-home-benefits', [['home.hero_benefits', 'all', 10]]);

        Livewire::test(Index::class)
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 0)
            ->assertSee('Početna — raspored blokova')->assertSee('Podnožje — blokovi')
            ->assertSee('Odabrana ponuda')->assertSee('Prednosti kupnje')
            ->assertSee('wire:click="openPreview('.$home->id.')"', false)
            ->assertSee('wire:click="disableBlock('.$footer->id.')"', false)
            ->assertDontSee('wire:click="delete('.$home->id.')"', false)
            ->assertDontSee('data-libermedia-other-blocks', false)
            ->assertSee(route('admin.content.blocks.create'), false);
    }

    public function test_other_blocks_and_matching_shared_placements_remain_accessible(): void
    {
        $this->block('home-only', [['home.hero', 'all', 10]]);
        $unplaced = $this->block('unplaced');
        $other = $this->block('other-page', [['page.top', 'desktop', 10]]);
        $mixed = $this->block('mixed', [['home.bottom', 'mobile', 10], ['page.bottom', 'desktop', 10]]);
        $targeted = $this->block('targeted', [['home.hero', 'all', 20, 'category', 'toys']]);

        Livewire::test(Index::class)
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === [$unplaced->id, $other->id, $mixed->id, $targeted->id])
            ->assertSee('data-libermedia-other-blocks', false)
            ->set('surface', 'desktop')
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === [$other->id, $mixed->id, $targeted->id])
            ->set('search', 'mixed')->assertSee('page.bottom');

        config(['storefront.brand' => 'herrera']);
        Livewire::test(Index::class)->assertViewHas('rows', fn ($rows) => $rows->total() === 5)
            ->assertDontSee('data-libermedia-widget-manager', false);
    }

    public function test_search_and_surface_filters_apply_to_home_footer_and_other_blocks(): void
    {
        $common = $this->block('common', [['home.hero', null, 10]]);
        $common->translations()->update(['title' => 'Posebna ponuda']);
        $desktop = $this->block('desktop', [['home.after_products', 'desktop', 10]]);
        $mobile = $this->block('mobile', [['home.after_products', 'mobile', 20]]);
        $footer = $this->block('footer', [['home.hero_benefits', 'desktop', 10]]);
        $this->block('other-mobile', [['page.top', 'mobile', 10]]);
        $ids = fn ($groups) => $groups->flatMap(fn ($group) => $group['slots'])->pluck('content_block_id')->all();

        Livewire::test(Index::class)->set('search', 'Posebna ponuda')
            ->assertViewHas('homeWidgetGroups', fn ($groups) => $ids($groups) === [$common->id])
            ->assertViewHas('footerWidgetSlots', fn ($slots) => $slots->isEmpty())
            ->set('search', '')->set('surface', 'desktop')
            ->assertViewHas('homeWidgetGroups', fn ($groups) => $ids($groups) === [$common->id, $desktop->id])
            ->assertViewHas('footerWidgetSlots', fn ($slots) => $slots->pluck('content_block_id')->all() === [$footer->id])
            ->set('surface', 'mobile')
            ->assertViewHas('homeWidgetGroups', fn ($groups) => $ids($groups) === [$common->id, $mobile->id])
            ->assertViewHas('footerWidgetSlots', fn ($slots) => $slots->isEmpty())
            ->set('search', 'nepostojeće')->assertSee('Nema blokova za odabrane filtre.')
            ->assertDontSee('data-libermedia-other-blocks', false);
    }

    public function test_move_uses_full_order_within_the_same_global_placement(): void
    {
        $first = $this->block('first', [['home.after_products', 'all', 10]]);
        $chosen = $this->block('chosen', [['home.after_products', 'all', 20]]);
        $last = $this->block('last', [['home.after_products', 'all', 30]]);
        $other = $this->block('other', [['home.bottom', 'all', 99]]);
        $targeted = $this->block('targeted', [['home.after_products', 'all', 15, 'category', 'toys']]);
        $slot = $chosen->slots()->sole();
        $cacheKey = config('content_blocks.cache.version_key');
        $beforeVersion = (int) Cache::get($cacheKey, 1);

        Livewire::test(Index::class)->set('search', 'chosen')
            ->assertViewHas('homeWidgetPositions', fn ($positions) => $positions[$slot->id] === 2)
            ->call('moveHomeWidget', $slot->id, 'up');

        $this->assertSame(10, $slot->fresh()->sort_order);
        $this->assertSame(20, $first->slots()->sole()->sort_order);
        $this->assertSame(30, $last->slots()->sole()->sort_order);
        $this->assertSame(99, $other->slots()->sole()->sort_order);
        $this->assertSame(15, $targeted->slots()->sole()->sort_order);
        $this->assertGreaterThan($beforeVersion, (int) Cache::get($cacheKey));
        $this->assertTrue(Activity::query()->where('description', 'Homepage block order changed.')->exists());

        Livewire::test(Index::class)->call('moveHomeWidget', $targeted->slots()->sole()->id, 'up')->assertNotFound();
    }

    public function test_toggle_preserves_other_placements_and_fixed_delete_only_disables_data(): void
    {
        $block = $this->block('libermedia-home-benefits', [['home.hero_benefits', 'all', 10], ['page.top', 'all', 20]]);
        $slot = $block->slots()->where('placement', 'home.hero_benefits')->sole();
        $path = resource_path('views/front/content-blocks/instances/libermedia-home-benefits.blade.php');
        $template = File::get($path);

        Livewire::test(Index::class)->call('toggleHomeWidget', $slot->id);
        $this->assertFalse($slot->fresh()->is_active);
        $this->assertTrue($block->fresh()->is_active);
        $this->assertTrue($block->slots()->where('placement', 'page.top')->sole()->is_active);

        Livewire::test(Index::class)->call('delete', $block->id);
        $this->assertDatabaseHas('content_blocks', ['id' => $block->id, 'is_active' => false]);
        $this->assertSame(0, $block->slots()->where('is_active', true)->count());
        $this->assertSame($template, File::get($path));
        Livewire::test(Index::class)->call('toggleHomeWidget', $slot->id);
        $this->assertTrue($block->fresh()->is_active);
        $this->assertTrue($slot->fresh()->is_active);
    }

    public function test_view_only_admin_can_preview_but_cannot_change_or_delete_widgets(): void
    {
        $block = $this->block('view-only', [['home.hero', 'all', 10]]);
        $viewer = User::factory()->create();
        Bouncer::allow($viewer)->to(['admin.access', 'content.blocks.view']);
        $this->actingAs($viewer);

        Livewire::test(Index::class)->call('openPreview', $block->id)->assertSet('previewBlockId', $block->id)
            ->assertDontSee('wire:click="toggleHomeWidget(', false)
            ->assertDontSee('wire:click="delete(', false)
            ->assertDontSee(route('admin.content.blocks.create'), false);
        Livewire::test(Index::class)->call('toggleHomeWidget', $block->slots()->sole()->id)->assertForbidden();
        Livewire::test(Index::class)->call('moveHomeWidget', $block->slots()->sole()->id, 'down')->assertForbidden();
        Livewire::test(Index::class)->call('delete', $block->id)->assertForbidden();
        $this->assertTrue($block->fresh()->is_active);
    }

    private function block(string $code, array $slots = []): ContentBlock
    {
        $block = ContentBlock::query()->create(['code' => $code, 'name' => $code, 'type' => 'rich_text', 'is_active' => true]);
        $block->translations()->create(['locale' => 'hr', 'title' => $code]);
        foreach ($slots as $definition) {
            [$placement, $variant, $order] = $definition;
            $block->slots()->create([
                'placement' => $placement, 'frontend_variant' => $variant, 'sort_order' => $order, 'is_active' => true,
                'target_type' => $definition[3] ?? null, 'target_ref' => $definition[4] ?? null,
            ]);
        }

        return $block;
    }
}
