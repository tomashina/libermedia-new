<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaAdminNavigationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_libermedia_navigation_keeps_shop_tools_and_hides_business_modules_even_when_enabled(): void
    {
        config(['storefront.brand' => 'libermedia', 'commerce.b2c_only' => false]);
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        foreach ([
            'admin.dashboard', 'admin.products', 'admin.categories', 'admin.orders',
            'admin.gift-vouchers', 'admin.withdrawals.index', 'admin.users',
            'admin.settings.system.store-settings',
        ] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
        foreach (['payment-methods', 'tax-rates', 'order-statuses'] as $resource) {
            $response->assertSee('href="'.route('admin.settings.local.resource', ['resource' => $resource]).'"', false);
        }
        $response->assertSee('href="'.route('admin.shipping.index').'"', false);

        foreach ([
            'admin.integrations.eprel.settings', 'admin.integrations.stock.index',
            'admin.integrations.eracuni.index', 'admin.integrations.spreadsheet.index',
            'admin.integrations.media.index', 'admin.settings.api.wholesale',
            'admin.b2b-prices', 'admin.b2b-prices.catalogs', 'admin.users.b2b',
            'admin.customer-offers.index', 'admin.content.supplier-catalogs.index',
            'admin.content.references.index',
        ] as $route) {
            $response->assertDontSee('href="'.route($route).'"', false);
        }
        $response->assertDontSeeText('Integracije')->assertDontSeeText('Wholesale API');
    }

    public function test_other_brands_keep_the_existing_navigation(): void
    {
        config(['storefront.brand' => 'herrera', 'commerce.b2c_only' => false]);
        $admin = $this->superadmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('href="'.route('admin.integrations.stock.index').'"', false)
            ->assertSee('href="'.route('admin.settings.api.wholesale').'"', false)
            ->assertSee('href="'.route('admin.users.b2b').'"', false)
            ->assertSee('href="'.route('admin.b2b-prices').'"', false);
    }

    public function test_libermedia_manual_follows_the_simplified_navigation(): void
    {
        config(['storefront.brand' => 'libermedia']);
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->get(route('admin.help.index'))->assertOk()
            ->assertSeeText('Upute prate Libermedia administraciju')
            ->assertDontSee('data-manual-section="integracije"', false)
            ->assertDontSeeText('B2B')
            ->assertDontSeeText('Wholesale API');

        foreach (['artikli', 'narudzbe', 'nacini-placanja', 'nacini-dostave', 'postavke-trgovine', 'lista-korisnika'] as $topic) {
            $response->assertSee('data-manual-entry="'.$topic.'"', false);
        }
        foreach (['b2b-cjenici', 'b2b-zahtjevi', 'wholesale-api', 'eprel-katalog', 'eprel-postavke'] as $topic) {
            $response->assertDontSee('data-manual-entry="'.$topic.'"', false);
        }

        $this->actingAs($admin)->get(route('admin.products'))->assertOk()
            ->assertSee('href="'.route('admin.help.index').'#artikli"', false);
    }

    private function superadmin(): User
    {
        app(SystemSettingsService::class)->put('catalog_use_api', true);
        $admin = User::factory()->create();
        Bouncer::assign('superadmin')->to($admin);

        return $admin;
    }
}
