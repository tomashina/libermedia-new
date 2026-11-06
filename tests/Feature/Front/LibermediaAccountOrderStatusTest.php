<?php

namespace Tests\Feature\Front;

use App\Livewire\Admin\Sales\Order\Show as OrderShow;
use App\Models\Sales\Offer\CustomerOffer;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\OrderStatus;
use App\Models\User;
use App\Services\Sales\CustomerOfferPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaAccountOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia', 'commerce.b2c_only' => true, 'app.locale' => 'hr', 'app.fallback_locale' => 'hr']);
        app()->setLocale('hr');
    }

    public function test_known_english_statuses_display_in_croatian_without_changing_raw_names_or_flags(): void
    {
        foreach ([
            'Pending' => 'U obradi', 'Processing' => 'U pripremi', 'Shipped' => 'Poslana', 'Complete' => 'Dovršena',
            'Canceled' => 'Otkazana', 'Canceled Reversal' => 'Poništen povrat sredstava', 'Chargeback' => 'Povrat terećenja',
            'Denied' => 'Odbijena', 'Expired' => 'Istekla', 'Failed' => 'Neuspjela', 'Refunded' => 'Sredstva vraćena',
            'Reversed' => 'Stornirana', 'Voided' => 'Poništena', 'Processed' => 'Obrađena', 'Paid' => 'Plaćena',
        ] as $name => $expected) {
            $status = new OrderStatus(['name' => $name, 'code' => 'imported', 'is_paid' => true, 'is_cancelled' => false]);
            $this->assertSame($expected, $status->display_name);
            $this->assertSame($name, $status->name);
            $this->assertTrue($status->is_paid);
            $this->assertFalse($status->is_cancelled);
        }
        $custom = new OrderStatus(['name' => 'Čeka potvrdu kupca', 'code' => 'pending']);
        $this->assertSame('Čeka potvrdu kupca', $custom->display_name);
        config(['storefront.brand' => 'herrera']);
        $this->assertSame('Pending', (new OrderStatus(['name' => 'Pending']))->display_name);
    }

    public function test_progress_shows_only_first_four_regular_steps_until_other_statuses_are_selected(): void
    {
        [$buyer, $order, $statuses] = $this->fixture();
        $expected = array_map(fn ($status) => $status->id, array_slice($statuses, 0, 4));
        $this->actingAs($buyer)->get(route('account.orders.show', ['orderNumber' => $order->order_number]))
            ->assertOk()->assertViewHas('statusSteps', fn ($steps) => $steps->modelKeys() === $expected)
            ->assertSee('U obradi')->assertSee('U pripremi')->assertSee('Poslana')->assertSee('Dovršena')
            ->assertDontSee('Pending')->assertDontSee('Processing')->assertDontSee('Shipped')->assertDontSee('Complete');
        $this->get(route('account.orders'))->assertOk()->assertSee('U obradi')->assertDontSee('Pending');
        $this->get(route('account.dashboard'))->assertOk()->assertSee('U obradi')->assertDontSee('Pending');
        $this->assertSame('Complete', $statuses[3]->fresh()->name);
        $this->assertTrue($statuses[3]->fresh()->is_paid);
        $this->assertFalse($statuses[3]->fresh()->is_cancelled);
    }

    public function test_current_and_historical_extra_statuses_are_visible_including_inactive_history_but_dropdowns_remain_complete(): void
    {
        [$buyer, $order, $statuses] = $this->fixture();
        $order->update(['status_id' => $statuses[4]->id]);
        $order->history()->create(['from_status_id' => $statuses[5]->id, 'to_status_id' => $statuses[7]->id]);
        $expected = collect($statuses)->only([0, 1, 2, 3, 4, 5, 7])->pluck('id')->values()->all();
        $this->actingAs($buyer)->get(route('account.orders.show', ['orderNumber' => $order->order_number]))
            ->assertOk()->assertViewHas('statusSteps', fn ($steps) => $steps->modelKeys() === $expected)
            ->assertSee('Otkazana')->assertSee('Sredstva vraćena')->assertSee('Neuspjela')->assertDontSee('Paid');
        $admin = User::factory()->create();
        Bouncer::assign('admin')->to($admin);
        Livewire::actingAs($admin)->test(OrderShow::class, ['orderId' => $order->id])
            ->assertViewHas('statuses', fn ($rows) => $rows->count() === 8 && $rows->contains('id', $statuses[6]->id))
            ->assertSee('Plaćena')->assertSee('Dostava dogovorena')->assertDontSee('Refunded');

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('account.orders.show', ['orderNumber' => $order->order_number]))->assertNotFound();
        config(['storefront.brand' => 'herrera']);
        $this->actingAs($buyer)->get(route('account.orders.show', ['orderNumber' => $order->order_number]))
            ->assertOk()->assertViewHas('statusSteps', fn ($steps) => $steps->count() === 8)->assertSee('Pending');
    }

    public function test_libermedia_offer_navigation_is_hidden_and_index_redirects_while_owner_pdf_links_remain_valid(): void
    {
        [$buyer, $order] = $this->fixture();
        $offer = CustomerOffer::create([
            'number' => 'PON-UX-1', 'customer_user_id' => $buyer->id, 'title' => 'Sačuvana ponuda', 'status' => 'sent',
            'valid_until' => now()->addDays(14)->toDateString(), 'customer_snapshot' => ['name' => $buyer->name],
            'priced_at' => now(), 'sent_at' => now(),
        ]);
        $before = $offer->refresh()->getRawOriginal();
        $indexUrl = route('account.customer-offers.index');
        foreach ([route('account.dashboard'), route('account.orders'), route('account.orders.show', ['orderNumber' => $order->order_number])] as $url) {
            $this->actingAs($buyer)->get($url)->assertOk()->assertDontSee('href="'.$indexUrl.'"', false);
        }
        $this->get($indexUrl)->assertRedirect(route('account.dashboard'));
        $this->mock(CustomerOfferPdf::class, function ($mock) use ($offer): void {
            $mock->shouldReceive('render')->once()->withArgs(fn (CustomerOffer $requested) => $requested->id === $offer->id)->andReturn('%PDF-1.4 preserved');
        });
        $this->get(route('account.customer-offers.pdf', $offer))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get(route('account.customer-offers.pdf', $offer))->assertNotFound();
        $this->assertSame($before, $offer->fresh()->getRawOriginal());

        config(['storefront.brand' => 'herrera']);
        $this->actingAs($buyer)->get($indexUrl)->assertOk()->assertSee($offer->number);
        $this->get(route('account.dashboard'))->assertOk()->assertSee('href="'.$indexUrl.'"', false);
    }

    private function fixture(): array
    {
        $statuses = [];
        foreach (['Pending', 'Processing', 'Shipped', 'Complete', 'Canceled', 'Refunded', 'Paid', 'Failed', 'Dostava dogovorena'] as $index => $name) {
            $statuses[] = OrderStatus::create([
                'code' => $name === 'Paid' ? 'paid' : 'imported-'.($index + 1), 'name' => $name,
                'sort_order' => $index * 10, 'is_active' => $name !== 'Failed',
                'is_paid' => $name === 'Complete', 'is_cancelled' => $name === 'Canceled', 'is_default' => $index === 0,
            ]);
        }
        $buyer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'LM-TEST-TIMELINE', 'user_id' => $buyer->id, 'status_id' => $statuses[0]->id,
            'customer_name' => $buyer->name, 'customer_email' => $buyer->email, 'grand_total' => 30, 'placed_at' => now(),
        ]);

        return [$buyer, $order, $statuses];
    }
}
