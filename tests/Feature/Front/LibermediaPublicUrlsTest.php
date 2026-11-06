<?php

namespace Tests\Feature\Front;

use App\Http\Middleware\LibermediaPublicUrls;
use App\Models\Catalog\Bundle\Bundle;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Product\Product;
use App\Models\Content\Page\InfoPage;
use App\Models\User\CustomerGroup;
use App\Services\Settings\SystemSettingsService;
use App\Support\Libermedia\PublicUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LibermediaPublicUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia', 'commerce.b2c_only' => true,
            'commerce.b2b_only' => false, 'storefront_cache.enabled' => false,
            'app.locale' => 'hr', 'app.fallback_locale' => 'hr', 'app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        app(SystemSettingsService::class)->putMany(['catalog_use_manufacturers' => true, 'catalog_use_blog' => true]);
        app()->setLocale('hr');
    }

    public function test_named_routes_generate_croatian_urls_without_changing_route_identity_or_slugs(): void
    {
        foreach ([
            'categories.show' => '/kategorija/igracke', 'products.show' => '/proizvod/igracke',
            'manufacturers.show' => '/brend/igracke', 'pages.show' => '/stranica/igracke',
            'pages.category' => '/stranice/kategorija/igracke',
        ] as $name => $expected) {
            $this->assertSame($expected, route($name, ['slug' => 'igracke'], absolute: false));
            $this->assertNotNull(Route::getRoutes()->getByName($name));
        }
        $this->assertSame('/trgovina', route('shop.index', absolute: false));
        $this->assertSame('/kategorije', route('categories.index', absolute: false));
        $this->assertSame('/brendovi', route('manufacturers.index', absolute: false));
        config(['storefront.brand' => 'herrera']);
        $this->assertSame('/category/igracke', route('categories.show', ['slug' => 'igracke'], absolute: false));
    }

    public function test_english_get_and_head_addresses_redirect_once_with_query_parameters(): void
    {
        foreach (['category' => 'kategorija', 'product' => 'proizvod', 'manufacturer' => 'brend', 'page' => 'stranica'] as $from => $to) {
            $this->get('http://localhost/'.$from.'/igracke?page=2&utm_source=test')->assertStatus(301)
                ->assertRedirect('/'.$to.'/igracke?page=2&utm_source=test');
            $this->head('http://localhost/'.$from.'/igracke')->assertStatus(301)->assertRedirect('/'.$to.'/igracke');
        }
        $this->get('http://localhost/shop?q=kocke')->assertStatus(301)->assertRedirect('/trgovina?q=kocke');
        $this->get('http://localhost/shop')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('http://localhost/categories')->assertStatus(301)->assertRedirect('/kategorije');
        $this->get('http://localhost/pages/category/odgoj')->assertStatus(301)->assertRedirect('/stranice/kategorija/odgoj');
        $this->get('/admin/nepoznato')->assertNotFound();
    }

    public function test_localized_product_route_serves_the_product_with_its_canonical_and_original_route_name(): void
    {
        $this->product();
        $this->get('/proizvod/drvene-bojice')->assertOk()
            ->assertSee('href="http://localhost/proizvod/drvene-bojice"', false)
            ->assertSee('property="og:url" content="http://localhost/proizvod/drvene-bojice"', false);
        $this->assertSame('libermedia.localized.products.show', Route::getRoutes()->getByName('libermedia.localized.products.show')->getName());
        $this->get('/proizvod/drvene-bojice')->assertOk();
    }

    public function test_real_opencart_source_aliases_and_index_requests_resolve_through_import_maps(): void
    {
        $product = $this->product();
        $category = Category::query()->create(['code' => 'toys', 'scope' => 'catalog', 'is_active' => true]);
        $category->translations()->create(['scope' => 'catalog', 'locale' => 'hr', 'name' => 'Igračke', 'slug' => 'didakticke-igracke']);
        $brand = Manufacturer::query()->create(['code' => 'jovi', 'is_active' => true]);
        $brand->translations()->create(['locale' => 'hr', 'name' => 'Jovi', 'slug' => 'jovi']);
        $page = InfoPage::query()->create(['code' => 'privacy', 'is_active' => true]);
        $page->translations()->create(['locale' => 'hr', 'title' => 'Privatnost', 'slug' => 'pravila-privatnosti']);

        // Public source IDs and aliases from the 2026-10-21 OpenCart snapshot.
        $cases = [
            ['product', 4, $product->id, 'drvene-bojice-debele-jovi-trokutastog-oblika-pakiranje-1-12', 'product_id=4', '/proizvod/drvene-bojice', 'route=product/product&product_id=4'],
            ['category', 638, $category->id, 'didakticke-igracke', 'category_id=638', '/kategorija/didakticke-igracke', 'route=product/category&path=600_638'],
            ['manufacturer', 1, $brand->id, 'jovi', 'manufacturer_id=1', '/brend/jovi', 'route=product/manufacturer/info&manufacturer_id=1'],
            ['information', 3, $page->id, 'pravila-privatnosti', 'information_id=3', '/stranica/pravila-privatnosti', 'route=information/information&information_id=3'],
        ];
        foreach ($cases as [$entity, $source, $target, $alias, $query, $destination, $parameters]) {
            $this->mapping($entity, $source, $target);
            $this->alias('/'.$alias, $query);
            $this->get('/'.$alias)->assertStatus(301)->assertRedirect($destination);
            $this->get('/index.php?'.$parameters.'&utm_source=old-store')->assertStatus(301)->assertRedirect($destination.'?utm_source=old-store');
            $this->get($destination)->assertOk();
        }
        $this->get('/didakticke-igracke/drvene-bojice-debele-jovi-trokutastog-oblika-pakiranje-1-12')->assertStatus(301)->assertRedirect('/proizvod/drvene-bojice');
        $this->get('/random/drvene-bojice-debele-jovi-trokutastog-oblika-pakiranje-1-12')->assertNotFound();
        $this->get('/index.php?route=product/search&search=kocke')->assertStatus(301)->assertRedirect('/trgovina?q=kocke');
        $this->get('/index.php?route=account/register')->assertStatus(301)->assertRedirect('/auth/register');
    }

    public function test_preserved_diacritic_alias_and_index_route_alias_have_one_canonical_target(): void
    {
        $this->product(10, 'pastele-vostane-carobni-medo-pak-1-30');
        $this->alias('/pastele-voštane-čarobni-medo-pak-1-30', 'product_id=10');
        $this->get('/pastele-vo%C5%A1tane-%C4%8Darobni-medo-pak-1-30')->assertStatus(301)->assertRedirect('/proizvod/pastele-vostane-carobni-medo-pak-1-30');
        $this->get('/index.php?_route_=pastele-vo%C5%A1tane-%C4%8Darobni-medo-pak-1-30')->assertStatus(301)->assertRedirect('/proizvod/pastele-vostane-carobni-medo-pak-1-30');
    }

    public function test_query_only_source_ids_work_without_alias_rows_or_index_php_in_the_destination(): void
    {
        $product = $this->product();
        $this->mapping('product', 3102, $product->id);
        $this->assertDatabaseCount('herrera_legacy_urls', 0);
        $this->withServerVariables([
            'SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ])->get('/index.php?route=product/product&product_id=3102')
            ->assertStatus(301)->assertHeader('Location', 'http://localhost/proizvod/drvene-bojice');
        $this->withServerVariables([
            'SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '/index.php/product/drvene-bojice',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ])->get('/index.php/product/drvene-bojice')
            ->assertStatus(301)->assertHeader('Location', 'http://localhost/proizvod/drvene-bojice');
    }

    public function test_known_removed_conflicted_and_malformed_sources_do_not_redirect_to_home(): void
    {
        $product = $this->product();
        $this->alias('/removed', 'product_id=4');
        $this->alias('/conflict', 'product_id=4', 'conflict');
        $product->update(['is_active' => false]);
        $this->get('/removed')->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/conflict')->assertStatus(410);
        $this->get('/unknown-url')->assertNotFound();
        $this->get('/index.php?route[]=product/product&product_id=4')->assertStatus(410);
        $this->get('/index.php?route=product/category&path[]=638')->assertStatus(410);
        $this->get('/index.php?route=product/product&product_id[]=4')->assertStatus(410);
    }

    public function test_previously_unresolved_bundle_and_return_aliases_use_proven_sources(): void
    {
        $group = CustomerGroup::query()->create(['code' => 'public', 'name' => 'Kupci', 'is_active' => true, 'is_default' => true]);
        $bundle = Bundle::query()->create(['source_id' => 3, 'title' => 'Magnetne pločice', 'is_active' => true]);
        $bundle->discounts()->create(['customer_group_id' => $group->id, 'type' => 'percent', 'value' => 10]);
        $this->alias('/magnetne-plocice-basic-magnetne-plocice-brojevi', 'bundle_id=3', 'unresolved');
        $this->alias('/obrazac-za-povrat', 'account/return/add', 'unresolved');
        $this->get('/magnetne-plocice-basic-magnetne-plocice-brojevi')->assertStatus(301)->assertRedirect('/bundle-artikli#bundle-'.$bundle->id);
        $this->get('/obrazac-za-povrat')->assertStatus(301)->assertRedirect('/forma-za-povrat-i-reklamacije');
        $bundle->update(['is_active' => false]);
        $this->get('/magnetne-plocice-basic-magnetne-plocice-brojevi')->assertStatus(410);
    }

    public function test_bundle_alias_lands_on_its_public_card_page_and_excludes_other_group_only_offers(): void
    {
        $public = CustomerGroup::query()->create(['code' => 'public', 'name' => 'Kupci', 'is_active' => true, 'is_default' => true]);
        $private = CustomerGroup::query()->create(['code' => 'private', 'name' => 'Posebna grupa', 'is_active' => true, 'is_default' => false]);
        for ($index = 0; $index < 12; $index++) {
            $preceding = Bundle::query()->create(['title' => 'Komplet '.$index, 'sort_order' => 1, 'is_active' => true]);
            $preceding->discounts()->create(['customer_group_id' => $public->id, 'type' => 'percent', 'value' => 10]);
        }
        $target = Bundle::query()->create(['source_id' => 3, 'title' => 'Magnetne pločice', 'sort_order' => 1, 'is_active' => true]);
        $target->discounts()->create(['customer_group_id' => $public->id, 'type' => 'percent', 'value' => 10]);
        $excluded = Bundle::query()->create(['source_id' => 4, 'title' => 'Posebni komplet', 'sort_order' => 0, 'is_active' => true]);
        $excluded->discounts()->create(['customer_group_id' => $private->id, 'type' => 'percent', 'value' => 10]);
        $this->alias('/magnetne-plocice', 'bundle_id=3');
        $this->alias('/posebni-komplet', 'bundle_id=4');

        $this->get('/magnetne-plocice?page=9&utm_source=old')->assertStatus(301)
            ->assertRedirect('/bundle-artikli?page=2&utm_source=old#bundle-'.$target->id);
        $this->get('/bundle-artikli?page=2')->assertOk()->assertSee('id="bundle-'.$target->id.'"', false);
        $this->get('/bundle-artikli')->assertOk()->assertDontSee('id="bundle-'.$target->id.'"', false);
        $this->get('/posebni-komplet')->assertStatus(410);
        $target->discounts()->update(['value' => 0]);
        $this->get('/magnetne-plocice')->assertStatus(410);
    }

    public function test_sitemap_contains_only_current_canonical_product_paths(): void
    {
        $this->product();
        $this->get('/sitemap-products-1.xml')->assertOk()
            ->assertSee('/proizvod/drvene-bojice', false)->assertDontSee('/product/drvene-bojice', false);
    }

    public function test_rendered_href_rewriting_preserves_external_signed_script_and_other_attributes(): void
    {
        $signed = URL::temporarySignedRoute('gift-vouchers.purchase', now()->addHour(), ['orderNumber' => 'LM-123']);
        $this->assertTrue(URL::hasValidSignature(Request::create($signed)));
        $this->assertSame($signed, PublicUrls::url($signed));
        $this->assertSame('/product/toys?signature=retained', PublicUrls::url('/product/toys?signature=retained'));
        $unchanged = '<a href="https://external.test/category/toys">External</a><a href="//localhost/category/toys">Protocol</a>'
            .'<a href="https://user@localhost/category/toys">Credentials</a><img src="/product/photo.jpg">'
            .'<form action="/product/toys/comments"></form><script>const html = \'<a href="/category/toys">x</a>\';</script>'
            .'<a href="'.htmlspecialchars($signed, ENT_QUOTES, 'UTF-8').'">Signed</a>';
        $html = '<a href="/category/toys?page=2&amp;q=lego#details">Toys</a>'.$unchanged;
        $localized = PublicUrls::html($html);
        $this->assertStringStartsWith('<a href="/kategorija/toys?page=2&amp;q=lego#details">Toys</a>', $localized);
        $this->assertStringEndsWith($unchanged, $localized);
        $this->assertSame($localized, PublicUrls::html($localized));
        $this->assertSame('/%2Fcategory/toys', PublicUrls::url('/%2Fcategory/toys'));
        $this->assertSame('<a title=\'Example href="/category/toys" > details\' data-href="/category/toys" href="/kategorija/toys">Toys</a>',
            PublicUrls::html('<a title=\'Example href="/category/toys" > details\' data-href="/category/toys" href="/category/toys">Toys</a>'));
    }

    public function test_html_rewriting_only_applies_to_public_html_responses(): void
    {
        $middleware = app(LibermediaPublicUrls::class);
        foreach ([['/admin/products', 'text/html'], ['/api/items', 'text/html'], ['/trgovina', 'application/json']] as [$path, $type]) {
            $html = '<a href="/category/toys">Toys</a>';
            $response = $middleware->handle(Request::create($path), fn () => new Response($html, 200, ['Content-Type' => $type]));
            $this->assertSame($html, $response->getContent());
        }
        $response = $middleware->handle(Request::create('/trgovina'), fn () => new Response('<a href="/category/toys">Toys</a>', 200, ['Content-Type' => 'text/html']));
        $this->assertSame('<a href="/kategorija/toys">Toys</a>', $response->getContent());
    }

    private function product(int $sourceId = 4, string $slug = 'drvene-bojice'): Product
    {
        $product = Product::query()->create(['code' => 'JOVI-4', 'sku' => 'JOVI-4', 'is_active' => true, 'base_price' => 10, 'stock_qty' => 10]);
        $product->translations()->create(['locale' => 'hr', 'name' => 'Drvene bojice', 'slug' => $slug]);
        $this->mapping('product', $sourceId, $product->id);

        return $product;
    }

    private function mapping(string $entity, int $source, int $target): void
    {
        DB::table('herrera_import_maps')->updateOrInsert(['source' => 'herrera-opencart', 'entity' => $entity, 'source_id' => (string) $source],
            ['target_id' => $target, 'checksum' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function alias(string $path, string $query, string $status = 'redirect'): void
    {
        DB::table('herrera_legacy_urls')->insert(['path' => $path, 'path_hash' => hash('sha256', $path), 'locale' => 'hr',
            'source_query' => $query, 'status' => $status, 'destination' => null, 'created_at' => now(), 'updated_at' => now()]);
    }
}
