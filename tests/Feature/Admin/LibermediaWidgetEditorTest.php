<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureAdminAbility;
use App\Livewire\Admin\Content\Block\Form;
use App\Models\Catalog\Product\Product;
use App\Models\Content\ContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaWidgetEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia', 'app.locale' => 'hr', 'app.fallback_locale' => 'hr']);
        app()->setLocale('hr');
    }

    public function test_widget_editor_shows_content_controls_and_preserves_identity_templates_and_other_payload(): void
    {
        $block = $this->block('campaigns');
        $block->update(['payload' => ['source' => ['keep' => true]]]);
        $translation = $block->translations()->firstOrFail();
        $translation->update(['body_html' => '<p>Historical content</p>', 'payload' => [
            'source' => 'keep', 'bg_css' => 'background:orange', 'libermedia' => ['unknown_legacy' => 'keep'],
        ]]);
        $slot = $block->slots()->firstOrFail();
        $slot->update(['target_type' => 'category', 'target_ref' => '7']);
        $secondSlot = $block->slots()->create(['placement' => 'home.bottom', 'sort_order' => 20, 'is_active' => true]);
        $path = resource_path('views/front/content-blocks/instances/'.$block->code.'.blade.php');
        $template = File::get($path);
        $forgedPath = resource_path('views/front/content-blocks/instances/forged-widget-identity.blade.php');

        Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id])
            ->assertSee('Uredite tekstove, slike, poveznice i prikaz widgeta.')
            ->assertDontSeeHtml('wire:model="form.code"')
            ->assertDontSeeHtml('wire:model.live="form.type"')
            ->assertDontSeeHtml('wire:model="form.bg_css"')
            ->assertDontSeeHtml('wire:model="form.slot_target_type"')
            ->assertSet('form.template_body', '')
            ->assertSet('form.libermedia.bag_cta_icon', 'arrow-right')
            ->set('form.code', 'forged-widget-identity')
            ->set('form.type', 'banner')->assertSet('form.type', 'rich_text')
            ->set('form.template_body', '<section>Forged source</section>')
            ->set('form.bg_css', 'background:black')
            ->set('form.slot_target_type', 'product')->set('form.slot_target_ref', '999')
            ->set('form.title', 'Nova odabrana ponuda')
            ->set('form.libermedia.bag_cta_url', '/category/skolske-torbe')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.content.blocks'));

        $this->assertSame('libermedia-home-campaigns', $block->fresh()->code);
        $this->assertSame('rich_text', $block->fresh()->type);
        $this->assertSame(['source' => ['keep' => true]], $block->fresh()->payload);
        $this->assertSame($template, File::get($path));
        $this->assertFileDoesNotExist($forgedPath);
        $this->assertSame('<p>Historical content</p>', $translation->fresh()->body_html);
        $this->assertSame('Nova odabrana ponuda', $translation->fresh()->title);
        $this->assertSame('keep', $translation->fresh()->payload['source']);
        $this->assertSame('background:orange', $translation->fresh()->payload['bg_css']);
        $this->assertSame('keep', $translation->fresh()->payload['libermedia']['unknown_legacy']);
        $this->assertSame('/category/skolske-torbe', $translation->fresh()->payload['libermedia']['bag_cta_url']);
        $this->assertSame('category', $slot->fresh()->target_type);
        $this->assertSame('7', $slot->fresh()->target_ref);
        $this->assertSame('home.bottom', $secondSlot->fresh()->placement);
        $this->assertSame(2, $block->slots()->count());
    }

    #[DataProvider('unsafeWidgetValues')]
    public function test_widget_rejects_unsafe_links_images_icons_and_unknown_fields(string $field, mixed $value, string $error): void
    {
        $block = $this->block('campaigns');
        Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id])
            ->set($field, $value)->call('save')->assertHasErrors([$error]);
        $this->assertArrayNotHasKey('libermedia', $block->translations()->firstOrFail()->payload ?? []);
    }

    public static function unsafeWidgetValues(): array
    {
        return [
            'script link' => ['form.libermedia.bag_cta_url', 'javascript:alert(1)', 'form.libermedia.bag_cta_url'],
            'protocol relative image' => ['form.libermedia.meli_image', '//example.test/image.png', 'form.libermedia.meli_image'],
            'data image' => ['form.libermedia.meli_image', 'data:image/svg+xml,<svg onload="alert(1)">', 'form.libermedia.meli_image'],
            'credential URL' => ['form.libermedia.bag_cta_url', 'https://user:secret@example.test/', 'form.libermedia.bag_cta_url'],
            'unsafe icon' => ['form.libermedia.bag_cta_icon', 'arrow-right" onload="alert(1)', 'form.libermedia.bag_cta_icon'],
            'unknown setting' => ['form.libermedia.template_path', '../unexpected.blade.php', 'form.libermedia'],
        ];
    }

    public function test_widget_image_upload_is_stored_on_public_disk_and_does_not_replace_templates(): void
    {
        Storage::fake('public');
        $block = $this->block('campaigns');
        Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id])
            ->set('libermediaImageUploads.meli_image', UploadedFile::fake()->image('meli.jpg', 600, 400))
            ->call('save')->assertHasNoErrors();

        $path = $block->translations()->firstOrFail()->payload['libermedia']['meli_image'];
        $this->assertStringStartsWith('/storage/libermedia-widgets/', $path);
        Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));
    }

    public function test_widget_rejects_non_image_upload_without_attempting_a_preview(): void
    {
        Storage::fake('public');
        $block = $this->block('campaigns');
        Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id])
            ->set('libermediaImageUploads.meli_image', UploadedFile::fake()->create('script.php', 1, 'application/x-php'))
            ->call('save')->assertHasErrors(['libermediaImageUploads.meli_image']);
        $this->assertArrayNotHasKey('libermedia', $block->translations()->firstOrFail()->payload ?? []);
        $this->assertSame([], Storage::disk('public')->allFiles('libermedia-widgets'));
    }

    public function test_product_widget_save_preserves_item_ids_and_other_item_types_while_reordering_products(): void
    {
        $block = $this->block('creative-products', 'products_carousel');
        $first = Product::query()->create(['code' => 'FIRST', 'base_price' => 10, 'stock_qty' => 1]);
        $second = Product::query()->create(['code' => 'SECOND', 'base_price' => 20, 'stock_qty' => 1]);
        $firstItem = $block->items()->create(['item_type' => 'product', 'item_id' => $first->id, 'sort_order' => 0]);
        $secondItem = $block->items()->create(['item_type' => 'product', 'item_id' => $second->id, 'sort_order' => 10]);
        $otherItem = $block->items()->create(['item_type' => 'category', 'item_id' => 123, 'sort_order' => 20]);

        Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id])
            ->assertSet('form.selected_item_ids', [$first->id, $second->id])
            ->assertSet('form.items_limit', 8)
            ->call('clearLibermediaSelectedItem', $first->id)
            ->assertSet('form.selected_item_ids', [$second->id])
            ->set('form.selected_item_ids', [$second->id, $first->id])->set('form.items_limit', 2)
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, $firstItem->fresh()->sort_order);
        $this->assertSame(0, $secondItem->fresh()->sort_order);
        $this->assertNotNull($otherItem->fresh());
        $this->assertSame(2, $block->translations()->firstOrFail()->payload['items_limit']);
    }

    public function test_widget_requires_update_permission_on_direct_mount_and_again_on_save(): void
    {
        $block = $this->block('campaigns');
        $viewer = User::factory()->create();
        Bouncer::allow($viewer)->to(['admin.access', 'content.blocks.view']);
        Livewire::actingAs($viewer)->test(Form::class, ['blockId' => $block->id])->assertForbidden();

        $component = Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id]);
        $this->actingAs($viewer);
        $component->set('form.title', 'Unauthorized change')->call('save')->assertForbidden();
        $this->assertNotSame('Unauthorized change', $block->translations()->firstOrFail()->title);
    }

    public function test_update_only_editor_can_clear_widget_images_and_selected_products(): void
    {
        $block = $this->block('campaigns');
        $editor = User::factory()->create();
        Bouncer::allow($editor)->to(['admin.access', 'content.blocks.update']);
        $this->assertFalse($editor->can('content.blocks.delete'));

        foreach (['clearLibermediaImage', 'clearLibermediaSelectedItem'] as $method) {
            $request = Request::create(route('admin.content.blocks.edit', ['block' => $block->id]), 'GET', [
                'components' => [[
                    'snapshot' => json_encode(['memo' => ['path' => 'admin/content/blocks/'.$block->id.'/edit', 'method' => 'GET']], JSON_THROW_ON_ERROR),
                    'calls' => [['method' => $method]],
                ]],
            ]);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $request->setUserResolver(fn () => $editor);
            $response = app(EnsureAdminAbility::class)->handle($request, fn () => response('Allowed'));
            $this->assertSame(200, $response->getStatusCode());
        }

        Livewire::actingAs($editor)->test(Form::class, ['blockId' => $block->id])
            ->call('clearLibermediaImage', 'meli_image')
            ->assertSet('form.libermedia.meli_image', '')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('', $block->translations()->firstOrFail()->payload['libermedia']['meli_image']);
    }

    public function test_widget_block_identity_cannot_be_switched_from_the_browser(): void
    {
        $block = $this->block('campaigns');
        $component = Livewire::actingAs($this->admin())->test(Form::class, ['blockId' => $block->id]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('blockId', $block->id + 1);
    }

    private function block(string $suffix, string $type = 'rich_text'): ContentBlock
    {
        $block = ContentBlock::query()->create(['code' => 'libermedia-home-'.$suffix, 'name' => 'Liber Media '.$suffix, 'type' => $type, 'is_active' => true]);
        $block->translations()->create(['locale' => 'hr', 'title' => 'Existing title']);
        $block->slots()->create(['placement' => 'home.before_products', 'frontend_variant' => 'all', 'sort_order' => 10, 'is_active' => true]);

        return $block;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);

        return $admin;
    }
}
