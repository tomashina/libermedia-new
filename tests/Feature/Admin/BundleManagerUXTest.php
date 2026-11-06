<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureAdminAbility;
use App\Livewire\Admin\Catalog\Bundle\Manager;
use App\Models\Catalog\Bundle\Bundle;
use App\Models\Catalog\Product\Product;
use App\Models\User;
use App\Models\User\CustomerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class BundleManagerUXTest extends TestCase
{
    use RefreshDatabase;

    private int $productSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'auto', 'commerce.b2c_only' => false, 'app.timezone' => 'UTC']);
    }

    public function test_status_filters_and_unfiltered_counts_match_availability_at_datetime_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 10:00:00', 'UTC'));
        $open = Bundle::create(['title' => 'Open', 'is_active' => true]);
        $boundary = Bundle::create(['title' => 'Exact boundary', 'is_active' => true, 'starts_at' => now(), 'ends_at' => now()]);
        $scheduled = Bundle::create(['title' => 'Future', 'is_active' => true, 'starts_at' => now()->addSecond(), 'ends_at' => now()->addDay()]);
        $expired = Bundle::create(['title' => 'Expired', 'is_active' => true, 'ends_at' => now()->subSecond()]);
        $invalidInterval = Bundle::create(['title' => 'Old invalid interval', 'is_active' => true, 'starts_at' => now()->addDay(), 'ends_at' => now()->subDay()]);
        $disabled = Bundle::create(['title' => 'Disabled', 'is_active' => false, 'starts_at' => now()->addDay(), 'ends_at' => now()->subDay()]);
        $counts = ['total' => 6, 'active' => 2, 'scheduled' => 1, 'expired' => 2, 'disabled' => 1];
        $component = Livewire::actingAs($this->admin())->test(Manager::class)->assertViewHas('statusCounts', $counts);

        foreach ([
            'active' => [$open->id, $boundary->id], 'scheduled' => [$scheduled->id],
            'expired' => [$expired->id, $invalidInterval->id], 'disabled' => [$disabled->id],
        ] as $status => $ids) {
            $component->set('status', $status)
                ->assertViewHas('bundles', fn ($rows) => $rows->pluck('id')->all() === $ids)
                ->assertViewHas('statusCounts', $counts);
        }

        $component->set('status', 'active')->set('search', 'Missing bundle')
            ->assertViewHas('bundles', fn ($rows) => $rows->isEmpty())->assertViewHas('statusCounts', $counts);
        $this->assertSame([$open->id, $boundary->id], Bundle::available()->orderBy('id')->pluck('id')->all());
    }

    public function test_sorting_filters_and_search_reset_pagination_and_only_use_allowed_sort_columns(): void
    {
        $first = Bundle::create(['title' => 'Charlie', 'sort_order' => 30, 'ends_at' => '2026-12-10', 'created_at' => '2026-11-01']);
        $second = Bundle::create(['title' => 'Alpha', 'sort_order' => 10, 'created_at' => '2026-11-03']);
        $third = Bundle::create(['title' => 'Bravo', 'sort_order' => 20, 'ends_at' => '2026-12-01', 'created_at' => '2026-11-02']);
        $component = Livewire::actingAs($this->admin())->test(Manager::class);
        foreach ([
            'order' => [$second->id, $third->id, $first->id], 'newest' => [$second->id, $third->id, $first->id],
            'title' => [$second->id, $third->id, $first->id], 'expiry' => [$third->id, $first->id, $second->id],
        ] as $sort => $ids) {
            $component->call('setPage', 2)->set('sort', $sort)->assertSet('paginators.page', 1)
                ->assertViewHas('bundles', fn ($rows) => $rows->pluck('id')->all() === $ids);
        }
        $component->call('setPage', 2)->set('search', 'Bravo')->assertSet('paginators.page', 1)
            ->assertViewHas('bundles', fn ($rows) => $rows->pluck('id')->all() === [$third->id])
            ->call('setPage', 2)->set('status', 'disabled')->assertSet('paginators.page', 1)
            ->set('sort', 'title; DROP TABLE catalog_bundles')->assertSet('sort', 'order')
            ->set('status', 'unexpected')->assertSet('status', '')
            ->call('clearFilters')->assertSet('search', '')->assertSet('status', '')->assertSet('sort', 'order');
        $this->assertDatabaseCount('catalog_bundles', 3);
    }

    public function test_product_picker_searches_code_sku_and_name_with_a_bounded_eager_loaded_result_set(): void
    {
        $code = $this->product(['code' => 'ONLY-CODE', 'sku' => 'UNRELATED-1']);
        $sku = $this->product(['code' => 'UNRELATED-2', 'sku' => 'ONLY-SKU']);
        $name = $this->product(['code' => 'UNRELATED-3', 'sku' => 'UNRELATED-3']);
        $name->translations()->create(['locale' => 'hr', 'name' => 'Samo poseban naziv', 'slug' => 'samo-poseban-naziv']);
        $component = Livewire::actingAs($this->admin())->test(Manager::class)->call('create');
        foreach (['ONLY-CODE' => $code->id, 'ONLY-SKU' => $sku->id, 'poseban naziv' => $name->id] as $search => $id) {
            $component->set('productSearch', $search)
                ->assertViewHas('productResults', fn ($rows) => $rows->pluck('id')->all() === [$id]
                    && $rows->first()->relationLoaded('translations') && $rows->first()->relationLoaded('media'));
        }
        foreach (range(1, 20) as $index) {
            $this->product(['code' => 'MATCH-CODE-'.$index, 'sku' => 'MATCH-SKU-'.$index]);
        }
        $selected = Product::where('code', 'MATCH-CODE-1')->firstOrFail();
        $component->call('addProduct', $selected->id)->set('productSearch', ' MATCH ')
            ->assertViewHas('productResults', fn ($rows) => $rows->count() === 15 && ! $rows->contains('id', $selected->id))
            ->assertViewHas('selectedProducts', fn ($rows) => $rows->get($selected->id)->relationLoaded('media'))
            ->set('productSearch', 'M')->assertViewHas('productResults', fn ($rows) => $rows->isEmpty());
    }

    public function test_bundle_list_loads_product_previews_and_discount_groups_without_per_row_queries(): void
    {
        [$bundle, $group, $first] = $this->bundle();
        Livewire::actingAs($this->admin())->test(Manager::class)
            ->assertViewHas('bundles', fn ($rows) => $rows->first()->relationLoaded('products')
                && $rows->first()->products->first()->relationLoaded('media')
                && $rows->first()->products->first()->relationLoaded('translations')
                && $rows->first()->discounts->first()->relationLoaded('group')
                && $rows->first()->discounts->first()->group->id === $group->id)
            ->call('edit', $bundle->id)
            ->assertViewHas('selectedProducts', fn ($rows) => $rows->has($first->id));
    }

    public function test_cancel_editing_clears_draft_search_and_validation_without_clearing_list_filters(): void
    {
        [$bundle] = $this->bundle();
        Livewire::actingAs($this->admin())->test(Manager::class)
            ->set('search', 'Keep list filter')->set('status', 'active')->set('sort', 'newest')
            ->call('edit', $bundle->id)->set('title', '')->set('productSearch', 'draft')
            ->call('save')->assertHasErrors(['title'])
            ->call('cancelEditing')->assertHasNoErrors()
            ->assertSet('editing', false)->assertSet('editingId', null)->assertSet('title', '')
            ->assertSet('products', [])->assertSet('discounts', [])->assertSet('productSearch', '')
            ->assertSet('startsAt', null)->assertSet('endsAt', null)->assertSet('sortOrder', 0)
            ->assertSet('search', 'Keep list filter')->assertSet('status', 'active')->assertSet('sort', 'newest');
    }

    public function test_libermedia_b2c_save_preserves_hidden_groups_ids_source_payload_and_raw_title(): void
    {
        config(['storefront.brand' => 'libermedia']);
        [$bundle, $default, $first] = $this->bundle(['title' => 'Komplet A &amp; B', 'source_id' => 321, 'payload' => ['legacy' => ['keep' => true]]]);
        $other = CustomerGroup::create(['code' => 'HIDDEN', 'name' => 'Povijesna grupa', 'is_active' => false]);
        $hidden = $bundle->discounts()->create(['customer_group_id' => $other->id, 'type' => 'fixed', 'value' => 7.5]);
        $hiddenBefore = $hidden->refresh()->getRawOriginal();
        $defaultDiscountId = $bundle->discounts()->where('customer_group_id', $default->id)->value('id');
        $pivotId = DB::table('catalog_bundle_products')->where('bundle_id', $bundle->id)->where('product_id', $first->id)->value('id');

        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->assertSet('title', 'Komplet A &amp; B')->assertCount('discounts', 2)
            ->assertViewHas('isB2cEditor', true)->assertViewHas('defaultDiscountGroupId', $default->id)
            ->assertViewHas('defaultDiscountIndex', 0)
            ->set('discounts', [['customer_group_id' => $default->id, 'type' => 'percent', 'value' => 22]])
            ->call('save')->assertHasNoErrors()->assertSet('editing', false)->assertSet('editingId', null);

        $this->assertSame($hiddenBefore, $hidden->fresh()->getRawOriginal());
        $this->assertDatabaseHas('catalog_bundle_discounts', ['id' => $defaultDiscountId, 'bundle_id' => $bundle->id, 'value' => 22]);
        $this->assertSame($pivotId, DB::table('catalog_bundle_products')->where('bundle_id', $bundle->id)->where('product_id', $first->id)->value('id'));
        $this->assertSame('Komplet A &amp; B', $bundle->fresh()->title);
        $this->assertSame(321, $bundle->fresh()->source_id);
        $this->assertSame(['keep' => true], $bundle->fresh()->payload['legacy']);

        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->set('discounts.1.value', 999)->call('save')->assertHasNoErrors();
        $this->assertSame($hiddenBefore, $hidden->fresh()->getRawOriginal());
    }

    public function test_libermedia_editor_adds_a_missing_default_discount_and_reports_missing_default_group(): void
    {
        config(['storefront.brand' => 'libermedia']);
        [$bundle, $default] = $this->bundle();
        $other = CustomerGroup::create(['code' => 'OTHER', 'name' => 'Druga grupa', 'is_active' => true]);
        $bundle->discounts()->delete();
        $bundle->discounts()->create(['customer_group_id' => $other->id, 'type' => 'fixed', 'value' => 5]);
        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->assertCount('discounts', 2)->assertViewHas('defaultDiscountIndex', 1)
            ->assertSet('discounts.1.customer_group_id', $default->id)
            ->set('discounts.1.value', 10)->call('save')->assertHasNoErrors();
        $this->assertSame(2, $bundle->discounts()->count());

        $default->update(['is_active' => false]);
        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->assertViewHas('defaultDiscountGroupId', null)->assertViewHas('defaultDiscountIndex', null)
            ->call('save')->assertHasErrors(['discounts']);
    }

    public function test_libermedia_editor_uses_zagreb_dates_and_preserves_existing_seconds_on_unchanged_save(): void
    {
        config(['storefront.brand' => 'libermedia']);
        [$bundle] = $this->bundle(['starts_at' => '2026-11-06 08:15:45', 'ends_at' => '2026-11-06 10:45:32']);
        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->assertSet('startsAt', '2026-11-06T09:15')->assertSet('endsAt', '2026-11-06T11:45')
            ->assertViewHas('displayTimezone', 'Europe/Zagreb')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('2026-11-06 08:15:45', $bundle->fresh()->getRawOriginal('starts_at'));
        $this->assertSame('2026-11-06 10:45:32', $bundle->fresh()->getRawOriginal('ends_at'));

        Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id)
            ->set('endsAt', '2026-11-06T11:50')->call('save')->assertHasNoErrors();
        $this->assertSame('2026-11-06 10:50:00', $bundle->fresh()->getRawOriginal('ends_at'));
    }

    public function test_view_only_flags_and_direct_actions_respect_permissions(): void
    {
        [$bundle] = $this->bundle();
        $viewer = $this->operator(['view']);
        Livewire::actingAs($viewer)->test(Manager::class)
            ->assertViewHas('canCreate', false)->assertViewHas('canUpdate', false)->assertViewHas('canDelete', false);
        foreach ([['create'], ['edit', $bundle->id], ['clearProduct', 0], ['clearDiscount', 0], ['save'], ['delete', $bundle->id]] as $call) {
            Livewire::actingAs($viewer)->test(Manager::class)->call(...$call)->assertForbidden();
        }
        $this->assertNotNull($bundle->fresh());
    }

    public function test_update_only_user_can_clear_editor_rows_without_delete_permission_or_middleware_rejection(): void
    {
        [$bundle] = $this->bundle();
        $editor = $this->operator(['view', 'update']);
        Livewire::actingAs($editor)->test(Manager::class)->call('edit', $bundle->id)
            ->assertViewHas('canCreate', false)->assertViewHas('canUpdate', true)->assertViewHas('canDelete', false)
            ->call('clearProduct', 0)->assertCount('products', 1)
            ->call('clearDiscount', 0)->assertCount('discounts', 0)->call('cancelEditing')->assertSet('editingId', null);
        foreach (['clearProduct', 'clearDiscount'] as $method) {
            $request = Request::create(route('admin.bundles'), 'GET', ['components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'admin/bundles', 'method' => 'GET']], JSON_THROW_ON_ERROR),
                'calls' => [['method' => $method]],
            ]]]);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $request->setUserResolver(fn () => $editor);
            $this->assertSame(200, app(EnsureAdminAbility::class)->handle($request, fn () => response('Allowed'))->getStatusCode());
        }
    }

    public function test_editing_identity_is_locked_against_browser_changes(): void
    {
        [$bundle] = $this->bundle();
        $component = Livewire::actingAs($this->admin())->test(Manager::class)->call('edit', $bundle->id);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('editingId', $bundle->id + 1);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Bouncer::assign('superadmin')->to($admin);

        return $admin;
    }

    private function operator(array $abilities): User
    {
        $user = User::factory()->create();
        Bouncer::allow($user)->to('admin.access');
        Bouncer::allow($user)->to(array_map(fn ($ability) => 'catalog.actions.'.$ability, $abilities));

        return $user;
    }

    private function product(array $attributes = []): Product
    {
        $code = 'PRODUCT-'.++$this->productSequence;

        return Product::create(array_replace(['code' => $code, 'sku' => $code, 'base_price' => 10, 'stock_qty' => 5, 'is_active' => true], $attributes));
    }

    private function bundle(array $attributes = []): array
    {
        $group = CustomerGroup::firstOrCreate(['code' => 'DEFAULT'], ['name' => 'Kupci', 'is_active' => true, 'is_default' => true]);
        $first = $this->product();
        $second = $this->product();
        $bundle = Bundle::create(array_replace(['title' => 'Komplet', 'is_active' => true], $attributes));
        $bundle->products()->attach([$first->id => ['quantity' => 2], $second->id => ['quantity' => 1]]);
        $bundle->discounts()->create(['customer_group_id' => $group->id, 'type' => 'percent', 'value' => 10]);

        return [$bundle, $group, $first, $second];
    }
}
