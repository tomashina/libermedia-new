<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Sales\Order\Manager as OrderManager;
use App\Livewire\Admin\User\Manager as UserManager;
use App\Models\Sales\Order\Order;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class AdminDirectoryUXTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_delete_buttons_and_user_edit_links_follow_the_existing_write_permissions(): void
    {
        $reader = $this->operator(['sales.orders.view', 'users.list.view']);
        $buyer = User::factory()->create(['name' => 'Directory Buyer']);
        $order = Order::create(['order_number' => 'UX-ORDER', 'user_id' => $buyer->id, 'customer_name' => $buyer->name, 'customer_email' => $buyer->email]);
        $editUrl = route('admin.users.edit', ['user' => $buyer->id]);

        Livewire::actingAs($reader)->test(OrderManager::class)
            ->assertViewHas('canDeleteOrders', false)->assertDontSeeHtml('wire:click="delete('.$order->id.')"');
        Livewire::actingAs($reader)->test(UserManager::class)
            ->assertViewHas('canEditUsers', false)->assertDontSeeHtml('href="'.$editUrl.'"');
        $this->actingAs($reader)->get(route('admin.users.show', ['user' => $buyer->id]))
            ->assertOk()->assertDontSee('href="'.$editUrl.'"', false);

        $editor = $this->operator(['sales.orders.view', 'sales.orders.update', 'users.list.view', 'users.profile.update']);
        Livewire::actingAs($editor)->test(OrderManager::class)
            ->assertViewHas('canDeleteOrders', true)->assertSeeHtml('wire:click="delete('.$order->id.')"');
        Livewire::actingAs($editor)->test(UserManager::class)
            ->assertViewHas('canEditUsers', true)->assertSeeHtml('href="'.$editUrl.'"');
        $this->actingAs($editor)->get(route('admin.users.show', ['user' => $buyer->id]))
            ->assertOk()->assertSee('href="'.$editUrl.'"', false);
    }

    public function test_libermedia_customer_directory_keeps_business_filters_collapsed_and_main_table_personal(): void
    {
        config(['storefront.brand' => 'libermedia']);
        $operator = $this->operator(['users.list.view']);
        $buyer = User::factory()->create(['name' => 'Personal Customer']);
        $profile = $buyer->profile()->create(['company' => 'Historical Company', 'oib' => '11000000001', 'customer_type' => 'Preserved Classification']);
        $buyer->addresses()->create(['type' => 'billing', 'city' => 'Zagreb', 'postal_code' => '10000']);
        $before = $profile->refresh()->getRawOriginal();

        $component = Livewire::actingAs($operator)->test(UserManager::class)->set('search', $buyer->email);
        $html = $component->html();
        $xpath = $this->xpath($html);
        $details = $xpath->query('//details[summary[contains(., "Napredni filtri")]]')->item(0);
        $this->assertNotNull($details);
        $this->assertFalse($details->hasAttribute('open'));
        $this->assertStringContainsString('Komercijalist', $details->textContent);
        $this->assertStringNotContainsString('minmax(30rem', $html);
        $headers = implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//table/thead/tr/th'))));
        $this->assertStringNotContainsString('Tvrtka / OIB', $headers);
        $this->assertStringNotContainsString('Komercijalist / lokacija', $headers);
        $component->assertSee('Personal Customer')->assertSee($buyer->email);

        $component->set('city', 'Zagreb')->assertViewHas('rows', fn ($rows) => $rows->pluck('id')->all() === [$buyer->id]);
        $details = $this->xpath($component->html())->query('//details[summary[contains(., "Napredni filtri")]]')->item(0);
        $this->assertTrue($details->hasAttribute('open'));
        $this->assertSame($before, $profile->fresh()->getRawOriginal());
    }

    public function test_profile_editor_does_not_offer_forbidden_superadmin_edit_links(): void
    {
        $editor = $this->operator(['users.list.view', 'users.profile.update']);
        $target = User::factory()->create();
        Bouncer::assign('superadmin')->to($target);
        $editUrl = route('admin.users.edit', ['user' => $target->id]);
        Livewire::actingAs($editor)->test(UserManager::class)->assertDontSeeHtml('href="'.$editUrl.'"');
        $this->actingAs($editor)->get(route('admin.users.show', ['user' => $target->id]))
            ->assertOk()->assertDontSee('href="'.$editUrl.'"', false);
    }

    public function test_libermedia_overview_keeps_commercial_records_in_a_closed_section_and_preserves_other_brand_layouts(): void
    {
        $operator = $this->operator(['users.list.view']);
        $buyer = User::factory()->create();
        $account = $buyer->b2bAccount()->create(['company_name' => 'Preserved Business', 'oib' => '11000000001']);
        $before = $account->refresh()->getRawOriginal();
        config(['storefront.brand' => 'libermedia', 'commerce.b2c_only' => true]);
        $html = $this->actingAs($operator)->get(route('admin.users.show', ['user' => $buyer->id]))->assertOk()->getContent();
        $details = $this->xpath($html)->query('//details[summary[contains(., "Poslovni podaci i evidencija kupca")]]')->item(0);
        $this->assertNotNull($details);
        $this->assertFalse($details->hasAttribute('open'));
        $this->assertStringContainsString('Preserved Business', $details->textContent);
        $this->assertStringNotContainsString('style="grid-template-columns: repeat(2', $html);
        $this->assertSame($before, $account->fresh()->getRawOriginal());

        config(['storefront.brand' => 'herrera', 'commerce.b2c_only' => false]);
        $html = $this->get(route('admin.users.show', ['user' => $buyer->id]))->assertOk()->getContent();
        $this->assertSame(0, $this->xpath($html)->query('//details[summary[contains(., "Poslovni podaci i evidencija kupca")]]')->count());
        Livewire::actingAs($operator)->test(UserManager::class)->assertSee('Tvrtka / OIB')->assertSee('Komercijalist / lokacija');
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function operator(array $abilities): User
    {
        $user = User::factory()->create();
        Bouncer::allow($user)->to(['admin.access', ...$abilities]);

        return $user;
    }
}
