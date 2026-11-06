<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Content\Block\Form as BlockForm;
use App\Models\Content\ContentBlock;
use App\Models\User;
use App\Services\Content\ContentBlockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaHomeModulesFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('homeModules')]
    public function test_admin_edits_home_module_content_without_changing_its_template(
        string $suffix,
        string $placement,
        string $copy,
        string $sectionClass,
        string $destination
    ): void {
        config(['app.locale' => 'hr', 'app.fallback_locale' => 'hr', 'storefront.brand' => 'libermedia', 'storefront_cache.enabled' => false]);
        app()->setLocale('hr');
        $user = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($user);

        $code = 'libermedia-home-'.$suffix;
        $path = resource_path('views/front/content-blocks/instances/'.$code.'.blade.php');
        $this->assertFileExists($path);
        $original = File::get($path);
        $definition = config('libermedia_widgets.'.$code);
        $this->assertIsArray($definition);

        $block = ContentBlock::query()->create([
            'code' => $code,
            'name' => 'Liber Media '.$suffix,
            'type' => 'rich_text',
            'is_active' => true,
            'payload' => ['retained' => 'block metadata'],
        ]);
        $block->translations()->create(['locale' => 'hr', 'title' => 'Old administrative label', 'body_html' => null, 'payload' => ['retained' => 'translation metadata']]);
        $slot = $block->slots()->create([
            'placement' => $placement,
            'frontend_variant' => 'all',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $field = null;
        foreach ($definition['content_defaults'] ?? [] as $key => $value) {
            if ($value === $copy) {
                $field = 'form.'.$key;
            }
        }
        foreach ($definition['fields'] ?? [] as $key => $value) {
            if (($value['default'] ?? null) === $copy) {
                $field = 'form.libermedia.'.$key;
            }
        }
        $this->assertNotNull($field, 'The original public copy must have an editable field.');
        $editedCopy = $copy.' – uređeno u administraciji';
        Livewire::actingAs($user)->test(BlockForm::class, ['blockId' => $block->id])
            ->assertSet($field, $copy)
            ->assertDontSee('wire:model="form.template_body"', false)
            ->set($field, $editedCopy)->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.content.blocks'));

        $this->assertSame($original, File::get($path));
        $this->assertSame('block metadata', data_get($block->fresh()->payload, 'retained'));
        $translation = $block->fresh()->translations()->where('locale', 'hr')->firstOrFail();
        $this->assertSame('translation metadata', data_get($translation->payload, 'retained'));
        $this->assertArrayHasKey('libermedia', $translation->payload);
        $this->assertNull($translation->body_html);
        $this->assertSame($placement, $slot->fresh()->placement);
        Livewire::actingAs($user)->test(BlockForm::class, ['blockId' => $block->id])
            ->assertSet($field, $editedCopy);

        $html = view('components.content-placement', [
            'items' => app(ContentBlockResolver::class)->forPlacement($placement, 'hr'),
            'storeSettings' => [],
        ])->render();
        $this->assertStringContainsString('class="'.$sectionClass.'"', $html);
        $this->assertStringContainsString($editedCopy, $html);
        $this->assertStringContainsString('href="'.$destination.'"', $html);
    }

    public static function homeModules(): array
    {
        return [
            'benefits' => ['benefits', 'home.hero_benefits', 'Sigurno plaćanje', 'lm-benefits', '/page/nacin-placanja-i-dostava'],
            'campaigns' => ['campaigns', 'home.before_products', 'Pronađite svoju novu avanturu', 'lm-campaign-section', '/category/skolske-torbe-i-ruksaci'],
            'kindergarten' => ['kindergarten', 'home.after_products', 'Saznajte više', 'lm-kindergarten', 'https://namjestajzavrtice.com/'],
            'assurance' => ['assurance', 'home.bottom', 'Više igre. Manje brige.', 'lm-assurance', '/page/opci-uvjeti-koristenja'],
        ];
    }
}
