<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Content\Blog\Form as BlogForm;
use App\Livewire\Admin\Content\Blog\Manager as BlogManager;
use App\Livewire\Admin\Settings\Local\ResourceManager;
use App\Livewire\Admin\Settings\System\CatalogFeatures;
use App\Livewire\Admin\Settings\System\StoreSettings;
use App\Models\Content\Blog\BlogPost;
use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaContentSettingsExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia']);
        app()->setLocale('hr');
    }

    public function test_hidden_wholesale_flag_is_preserved_when_visible_catalog_settings_are_reset_and_saved(): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->put('catalog_use_api', true);
        Livewire::actingAs($this->admin())->test(CatalogFeatures::class)
            ->assertDontSee(__('Use Wholesale API'))
            ->call('resetToDefaults')->assertSet('form.catalog_use_api', true)
            ->set('form.catalog_use_api', false)
            ->set('form.catalog_use_options', true)
            ->call('save')->assertHasNoErrors();

        $this->assertTrue((bool) $settings->get('catalog_use_api'));
        $this->assertTrue((bool) $settings->get('catalog_use_options'));
    }

    public function test_retail_pricing_screen_preserves_the_hidden_audience_flag_and_retains_protection_settings(): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->put('store_pricing_b2c_enabled', true);
        $component = Livewire::actingAs($this->admin())->test(StoreSettings::class)
            ->assertSee('Zaštita i analitika')
            ->set('tab', 'pricing')
            ->assertDontSee('wire:model="form.store_pricing_b2c_enabled"', false)
            ->assertDontSee('Odobreni B2B kupci')
            ->set('form.store_pricing_b2c_enabled', false)
            ->set('form.store_pricing_prices_include_tax', true)
            ->call('save')->assertHasNoErrors();
        $this->assertTrue((bool) $settings->get('store_pricing_b2c_enabled'));
        $this->assertTrue((bool) $settings->get('store_pricing_prices_include_tax'));
        $component->set('tab', 'integrations')->assertSee('reCAPTCHA v3')->assertSee('Google Analytics (GA4)');

        config(['storefront.brand' => 'herrera']);
        Livewire::actingAs($this->admin())->test(CatalogFeatures::class)->assertSee(__('Use Wholesale API'));
    }

    public function test_newsletter_shows_only_selected_provider_fields_and_preserves_existing_credentials(): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->putMany(['store_newsletter_provider' => 'database', 'store_newsletter_mailchimp_api_key' => 'existing-fixture-key']);
        $component = Livewire::actingAs($this->admin())->test(StoreSettings::class)->set('tab', 'newsletter')
            ->assertDontSee('wire:model="form.store_newsletter_mailchimp_api_key"', false)
            ->assertDontSee('wire:model="form.store_newsletter_klaviyo_api_key"', false)
            ->set('form.store_newsletter_title', 'Novosti iz trgovine')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('existing-fixture-key', $settings->get('store_newsletter_mailchimp_api_key'));
        $component->set('form.store_newsletter_provider', 'mailchimp')
            ->assertSee('wire:model="form.store_newsletter_mailchimp_api_key"', false)
            ->assertDontSee('wire:model="form.store_newsletter_klaviyo_api_key"', false);
        $component->set('form.store_newsletter_provider', 'klaviyo')
            ->assertSee('wire:model="form.store_newsletter_klaviyo_api_key"', false)
            ->assertDontSee('wire:model="form.store_newsletter_mailchimp_api_key"', false);
    }

    public function test_blog_validation_is_visible_even_when_the_invalid_field_is_on_another_tab(): void
    {
        Livewire::actingAs($this->admin())->test(BlogForm::class)
            ->set('form.code', 'invalid-blog')
            ->set('form.title', '')
            ->set('form.slug', 'invalid-blog')
            ->call('setTab', 'seo')
            ->call('save')
            ->assertHasErrors(['form.title' => 'required'])
            ->assertSee('Provjerite označena polja prije spremanja.')
            ->assertSee('role="alert"', false);
    }

    public function test_invalid_advanced_local_settings_remain_visible_with_an_error_summary(): void
    {
        $component = Livewire::actingAs($this->admin())->test(ResourceManager::class, ['resource' => 'currencies', 'createPage' => true])
            ->set('form.code', 'USD')->set('form.name', 'US dollar')
            ->set('form.settings_text', '{not-json')
            ->call('save')->assertHasErrors('form.settings_text')
            ->assertSee('Napredne postavke')
            ->assertSee('role="alert"', false);
        $document = new DOMDocument;
        @$document->loadHTML($component->html());
        $this->assertSame(1, (new DOMXPath($document))->query('//details[@open]//textarea')->length);
    }

    public function test_blog_list_uses_imported_cover_when_the_old_local_media_file_is_missing(): void
    {
        Storage::fake('public');
        Storage::fake('admin-blog-legacy');
        Http::preventStrayRequests();
        config(['legacy_media.enabled' => true, 'legacy_media.remote_only' => true, 'legacy_media.origin' => 'https://www.liber-media.hr', 'legacy_media.local_root' => Storage::disk('admin-blog-legacy')->path('')]);
        $post = BlogPost::query()->create(['code' => 'imported-blog-preview', 'is_active' => true, 'payload' => ['opencart' => ['image' => 'catalog/blog/cover.jpg']]]);
        $post->translations()->create(['locale' => 'hr', 'title' => 'Igra i učenje', 'slug' => 'igra-i-ucenje']);
        $media = $post->addMedia(UploadedFile::fake()->image('cover.jpg', 120, 120))
            ->withCustomProperties(['opencart_path' => 'catalog/blog/cover.jpg'])->toMediaCollection('blog_cover');
        Storage::disk('public')->deleteDirectory((string) $media->id);

        Livewire::actingAs($this->admin())->test(BlogManager::class)
            ->set('locale', 'hr')->set('search', 'Igra i učenje')
            ->assertSee('https://www.liber-media.hr/image/catalog/blog/cover.jpg', false)
            ->assertDontSee($media->getUrl(), false);
        Http::assertNothingSent();
    }

    public function test_active_content_and_settings_screens_render_for_the_libermedia_admin(): void
    {
        app(SystemSettingsService::class)->put('catalog_use_blog', true);
        $this->actingAs($this->admin());
        foreach ([
            '/admin/content/blog', '/admin/content/blog/create',
            '/admin/content/pages', '/admin/content/pages/create',
            '/admin/content/faqs', '/admin/content/faqs/create', '/admin/content/comments',
            '/admin/content/blocks', '/admin/content/navigation',
            '/admin/settings/system/store-settings', '/admin/settings/system/catalog-features',
            '/admin/settings/system/admin-appearance-controls', '/admin/settings/system/runtime',
            '/admin/settings/system/withdrawal-settings', '/admin/settings/user',
            '/admin/shipping', '/admin/shipping/create',
        ] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['payment-methods', 'geo-zones', 'geo-zone-countries', 'regions', 'currencies', 'tax-rates', 'order-statuses', 'languages'] as $resource) {
            $this->get('/admin/settings/local/'.$resource)->assertOk();
            $this->get('/admin/settings/local/'.$resource.'/create')->assertOk();
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'superadmin']);
        Bouncer::assign('superadmin')->to($user);

        return $user;
    }
}
