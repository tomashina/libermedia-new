<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Catalog\Product\Manager as ProductManager;
use App\Livewire\Admin\Media\Manager as MediaManager;
use App\Models\Catalog\Product\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LibermediaProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'storefront.brand' => 'libermedia',
            'legacy_media.enabled' => true,
            'legacy_media.remote_only' => true,
            'legacy_media.origin' => 'https://www.liber-media.hr',
            'legacy_media.directories' => ['catalog', 'api', 'uploads'],
        ]);
        Storage::fake('public');
        Storage::fake('legacy-admin-images');
        config(['legacy_media.local_root' => Storage::disk('legacy-admin-images')->path('')]);
        Http::preventStrayRequests();
    }

    public function test_list_and_media_show_imported_photos_without_local_media_files(): void
    {
        $product = $this->product();
        $media = $product->addMedia(UploadedFile::fake()->image('old.jpg', 120, 120))
            ->withCustomProperties(['opencart_path' => 'api/4.jpg'])
            ->toMediaCollection('product_main');
        Storage::disk('public')->deleteDirectory((string) $media->id);
        $payload = $product->fresh()->getRawOriginal('payload');

        Livewire::actingAs(User::factory()->create())->test(ProductManager::class)
            ->set('locale', 'hr')->set('search', 'LM-IMAGE')
            ->assertSee('https://www.liber-media.hr/image/api/4.jpg', false)
            ->assertDontSee($media->getUrl(), false);

        $this->manager($product)
            ->assertSee('https://www.liber-media.hr/image/api/4.jpg', false)
            ->assertSee('https://www.liber-media.hr/image/catalog/toys/gallery.jpg', false)
            ->assertDontSee($media->getUrl(), false)
            ->assertDontSee('data-image-edit-open', false)
            ->assertViewHas('importedProductImages', fn ($images) => $images->all() === ['https://www.liber-media.hr/image/catalog/toys/gallery.jpg']);

        $this->assertSame($payload, $product->fresh()->getRawOriginal('payload'));
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Http::assertNothingSent();
    }

    public function test_imported_gallery_without_media_records_remains_available_for_preview(): void
    {
        $product = $this->product();
        $this->manager($product)
            ->assertSee('Fotografije iz stare trgovine')
            ->assertSee('https://www.liber-media.hr/image/api/4.jpg', false)
            ->assertSee('https://www.liber-media.hr/image/catalog/toys/gallery.jpg', false)
            ->assertDontSee('unsafe.example')
            ->assertViewHas('importedProductImages', fn ($images) => $images->count() === 2);

        $this->assertDatabaseCount('media', 0);
        Http::assertNothingSent();
    }

    public function test_new_uploaded_photo_takes_precedence_and_keeps_its_editing_controls(): void
    {
        $product = $this->product();
        $media = $product->addMedia(UploadedFile::fake()->image('new-photo.jpg', 120, 120))
            ->toMediaCollection('product_main');

        Livewire::actingAs(User::factory()->create())->test(ProductManager::class)
            ->set('locale', 'hr')->set('search', 'LM-IMAGE')
            ->assertSee('new-photo', false)
            ->assertDontSee('https://www.liber-media.hr/image/api/4.jpg', false);
        $this->manager($product)
            ->assertSee($media->getUrl(), false)
            ->assertSee('data-image-edit-open', false)
            ->assertDontSee('data-admin-imported-product-images', false);

        Http::assertNothingSent();
    }

    public function test_energy_upload_sections_are_hidden_and_existing_documents_are_preserved(): void
    {
        $product = $this->product();
        $document = $product->addMedia(UploadedFile::fake()->createWithContent('label.pdf', '%PDF-1.4 fixture'))
            ->toMediaCollection('product_energy_label');

        $this->manager($product)
            ->assertViewHas('collections', fn ($collections) => isset($collections['product_main'], $collections['product_gallery'], $collections['product_documents'])
                && ! isset($collections['product_energy_label'], $collections['product_information_sheet']))
            ->assertDontSee('uploads.product_energy_label', false)
            ->assertDontSee('uploads.product_information_sheet', false)
            ->call('delete', $document->id);

        $this->assertDatabaseHas('media', ['id' => $document->id, 'collection_name' => 'product_energy_label']);
        $this->assertFileExists($document->getPath());

        config(['storefront.brand' => 'herrera']);
        $this->manager($product)
            ->assertViewHas('collections', fn ($collections) => isset($collections['product_energy_label'], $collections['product_information_sheet']));
    }

    private function manager(Product $product)
    {
        return Livewire::actingAs(User::factory()->create())->test(MediaManager::class, [
            'modelClass' => Product::class, 'modelId' => $product->id, 'locale' => 'hr',
        ]);
    }

    private function product(): Product
    {
        $product = Product::query()->create([
            'code' => 'LM-IMAGE', 'sku' => 'LM-IMAGE', 'is_active' => true, 'base_price' => 10,
            'payload' => ['opencart' => [
                'image' => 'api/4.jpg',
                'gallery_images' => [
                    ['image' => 'catalog/toys/gallery.jpg', 'sort_order' => 1],
                    ['image' => 'https://unsafe.example/private.jpg', 'sort_order' => 2],
                ],
            ]],
        ]);
        $product->translations()->create(['locale' => 'hr', 'name' => 'Igračka sa slikom', 'slug' => 'igracka-sa-slikom']);

        return $product;
    }
}
