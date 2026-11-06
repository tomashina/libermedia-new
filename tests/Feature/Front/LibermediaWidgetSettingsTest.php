<?php

namespace Tests\Feature\Front;

use App\Models\Content\ContentBlock;
use App\Models\Content\ContentBlockTranslation;
use App\Models\Settings\Local\ShippingMethod;
use App\Support\LibermediaWidgetSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibermediaWidgetSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_static_widgets_keep_their_displayed_copy_until_the_first_structured_save(): void
    {
        $block = new ContentBlock(['code' => 'libermedia-home-campaigns', 'name' => 'Liber Media – odabrana ponuda']);
        $translation = new ContentBlockTranslation(['title' => 'Liber Media – odabrana ponuda', 'subtitle' => null, 'payload' => []]);
        $settings = LibermediaWidgetSettings::forBlock($block, $translation);
        $this->assertSame('Pronađite svoju novu avanturu', $settings['title']);
        $this->assertSame('MALI SVIJET. VELIKE MOGUĆNOSTI.', $settings['subtitle']);

        $translation->fill(['title' => 'Nova ponuda', 'subtitle' => '', 'payload' => ['unrelated' => 'keep', 'libermedia' => []]]);
        $settings = LibermediaWidgetSettings::forBlock($block, $translation);
        $this->assertSame('Nova ponuda', $settings['title']);
        $this->assertSame('', $settings['subtitle']);
        $this->assertSame([], LibermediaWidgetSettings::definition('unknown-widget'));
        $this->assertSame([], LibermediaWidgetSettings::fields('unknown-widget'));
    }

    public function test_existing_carousels_keep_their_translated_title_and_call_to_action(): void
    {
        $block = new ContentBlock(['code' => 'libermedia-home-creative-products', 'name' => 'Naziv bloka']);
        $translation = new ContentBlockTranslation(['title' => 'Urednički naslov', 'cta_label' => 'Pogledajte igre', 'cta_url' => '/shop?category=toys', 'payload' => []]);
        $settings = LibermediaWidgetSettings::forBlock($block, $translation);
        $this->assertSame('Urednički naslov', $settings['title']);
        $this->assertSame('Pogledajte igre', $settings['cta_label']);
        $this->assertSame('/shop?category=toys', $settings['cta_url']);

        $translation->title = null;
        $this->assertSame('Naziv bloka', LibermediaWidgetSettings::forBlock($block, $translation)['title']);
        $this->assertSame('', LibermediaWidgetSettings::forBlock($block)['cta_label']);
    }

    public function test_default_campaign_kindergarten_and_assurance_views_keep_their_original_content(): void
    {
        $campaigns = $this->render('campaigns');
        foreach (['Pronađite svoju novu avanturu', 'Velike avanture<br>', 'počinju s dobrom torbom.', 'Male kocke.<br>', 'Učim svojim<br>', '/category/skolske-torbe-i-ruksaci', '/manufacturer/ame-group-marcin-hernas', '/assets/libermedia/home/montessori-play.webp'] as $content) {
            $this->assertStringContainsString($content, $campaigns);
        }
        $this->assertSame(3, substr_count($campaigns, '<article'));
        $this->assertSame(4, substr_count($campaigns, '<img'));

        $kindergarten = $this->render('kindergarten');
        foreach (['Oprema i namještaj<br>', 'za vrtiće', 'https://namjestajzavrtice.com/', 'Saznajte više', 'kindergarten-interior.webp', 'kindergarten-playground.webp', 'Pratimo vas kroz cijeli postupak, od izrade projekta do opremanja interijera i vanjskih igrališta.'] as $content) {
            $this->assertStringContainsString($content, $kindergarten);
        }
        $assurance = $this->render('assurance');
        foreach (['Više igre. Manje brige.', 'Naše <strong>didaktičke igračke</strong>', '<strong>povrat unutar 2 tjedna</strong>', 'Partneri i materijali kojima vjerujemo.', 'Uvjeti povrata', '/page/opci-uvjeti-koristenja'] as $content) {
            $this->assertStringContainsString($content, $assurance);
        }
    }

    public function test_custom_text_images_links_and_icons_are_rendered_safely(): void
    {
        $translation = new ContentBlockTranslation([
            'title' => '<img src=x onerror=alert(1)>', 'subtitle' => 'Nova garancija',
            'payload' => ['libermedia' => [
                'body' => "Tekst <strong>naglasak</strong>\n<script>alert(1)</script><strong onclick=alert(1)>atribut</strong>",
                'image' => 'javascript:alert(1)', 'point_1_icon' => '"><script>',
                'point_1_cta_label' => 'Poveznica', 'point_1_cta_url' => 'javascript:alert(1)',
            ]],
        ]);
        $html = $this->render('assurance', $translation);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('Tekst <strong>naglasak</strong><br>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<strong onclick', $html);
        $this->assertStringNotContainsString('src="javascript:', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);

        $settings = LibermediaWidgetSettings::forBlock(new ContentBlock(['code' => 'libermedia-home-assurance']), $translation);
        $this->assertSame('heart', $settings['point_1_icon']);
        $this->assertSame('', $settings['point_1_cta_url']);
        $this->assertSame('', $settings['image']);
    }

    public function test_shipping_stays_automatic_unless_the_editor_selects_manual_text(): void
    {
        ShippingMethod::query()->update(['is_active' => false]);
        ShippingMethod::query()->create(['code' => 'widget-shipping', 'name' => 'Dostava', 'service_type' => 'home_delivery', 'pricing_type' => 'flat', 'price' => 5, 'free_over' => 55, 'is_active' => true, 'settings' => ['destination_country_codes' => ['HR']]]);
        $translation = new ContentBlockTranslation(['payload' => ['libermedia' => [
            'benefit_1_title' => 'Urednička dostava', 'benefit_1_description' => 'Ručni opis',
        ]]]);
        $this->assertStringContainsString('Od 55 € u Hrvatskoj', $this->render('benefits', $translation));
        $translation->payload = ['libermedia' => ['auto_shipping' => false, 'benefit_1_title' => 'Urednička dostava', 'benefit_1_description' => 'Ručni opis']];
        $html = $this->render('benefits', $translation);
        $this->assertStringContainsString('Urednička dostava', $html);
        $this->assertStringContainsString('Ručni opis', $html);
        $this->assertStringNotContainsString('Od 55 €', $html);
    }

    public function test_safe_urls_reject_active_content_and_accept_expected_shop_destinations(): void
    {
        foreach (['javascript:alert(1)', 'data:image/svg+xml,<svg>', '//unsafe.example/image.jpg', 'https://user:password@example.test/image.jpg', "/path\nwith-control", '/\\unsafe.example/image.jpg'] as $url) {
            $this->assertSame('', LibermediaWidgetSettings::safeUrl($url));
            $this->assertSame('', LibermediaWidgetSettings::safeUrl($url, true));
        }
        $this->assertSame('/assets/new-image.webp', LibermediaWidgetSettings::safeUrl('/assets/new-image.webp', true));
        $this->assertSame('https://example.test/image.webp', LibermediaWidgetSettings::safeUrl('https://example.test/image.webp', true));
        $this->assertSame('mailto:shop@example.test', LibermediaWidgetSettings::safeUrl('mailto:shop@example.test'));
        $this->assertSame('', LibermediaWidgetSettings::safeUrl('mailto:shop@example.test', true));
    }

    private function render(string $suffix, ?ContentBlockTranslation $translation = null): string
    {
        return view('front.content-blocks.instances.libermedia-home-'.$suffix, [
            'block' => new ContentBlock(['code' => 'libermedia-home-'.$suffix]),
            'translation' => $translation,
        ])->render();
    }
}
