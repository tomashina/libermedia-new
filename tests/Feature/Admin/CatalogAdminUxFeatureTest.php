<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureAdminAbility;
use App\Livewire\Admin\Catalog\Product\Form as ProductForm;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CatalogAdminUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundles_and_actions_have_separate_active_navigation_links(): void
    {
        app(SystemSettingsService::class)->put('catalog_use_actions', true);
        $admin = $this->admin();
        foreach (['admin.bundles', 'admin.actions'] as $current) {
            $html = $this->actingAs($admin)->get(route($current))->assertOk()->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            foreach (['admin.bundles', 'admin.actions'] as $link) {
                $node = $xpath->query('//aside[@id="admin-sidebar"]//a[@href="'.route($link).'"]')->item(0);
                $this->assertNotNull($node);
                $this->assertSame($current === $link, str_contains($node->getAttribute('class'), 'is-active-leaf'));
            }
        }
    }

    public function test_product_validation_opens_the_tab_with_the_invalid_field(): void
    {
        config(['storefront.brand' => 'libermedia']);
        $component = Livewire::actingAs($this->admin())->test(ProductForm::class)
            ->set('form.code', 'VALIDATION-TABS')->set('form.name', 'Article')->set('form.slug', 'article')
            ->set('form.meta_title', str_repeat('x', 256))->call('setTab', 'media')
            ->call('save')->assertHasErrors(['form.meta_title' => 'max'])->assertSet('activeTab', 'seo')
            ->assertSee('role="alert"', false)->assertSeeHtml('wire:model="form.meta_title"');

        $component->set('form.meta_title', '')->set('form.name', '')->call('setTab', 'catalog')
            ->call('save')->assertHasErrors(['form.name' => 'required'])->assertSet('activeTab', 'content');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_discarding_an_unsaved_category_uses_update_permissions(): void
    {
        $editor = User::factory()->create();
        Bouncer::allow($editor)->to(['admin.access', 'catalog.products.update']);
        $this->assertFalse($editor->can('catalog.products.delete'));
        $request = Request::create('/admin/products/123/edit', 'POST', [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['path' => 'admin/products/123/edit', 'method' => 'GET']]),
                'calls' => [['method' => 'discardCategory']],
            ]],
        ]);
        $request->setUserResolver(fn () => $editor);
        $route = clone Route::getRoutes()->getByName('admin.products.edit');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $response = app(EnsureAdminAbility::class)->handle($request, fn () => new Response('ok'));
        $this->assertSame(200, $response->getStatusCode());
        Livewire::actingAs($editor)->test(ProductForm::class)->set('form.category_ids', [1, 2])
            ->call('discardCategory', 1)->assertSet('form.category_ids', [2]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);

        return $admin;
    }
}
