<?php

namespace Tests\Feature\Payments;

use App\Livewire\Admin\Settings\Local\ResourceManager;
use App\Mail\GiftVoucherPurchaseConfirmation;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\User;
use App\Services\Front\StoreNotificationService;
use App\Services\Payments\BankTransferUpiService;
use App\Services\Payments\EpcQrService;
use App\Services\Payments\Hub3BarcodeService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LibermediaBankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['storefront.brand' => 'libermedia', 'commerce.local_safe_mode' => true]);
        Http::preventStrayRequests();
    }

    public function test_imported_description_backfills_empty_snapshots_and_generates_both_codes_offline(): void
    {
        $this->method();
        $order = $this->order(['payload' => ['bank_transfer' => ['receiver_iban' => '', 'instructions' => '']]]);
        $service = app(BankTransferUpiService::class);
        $snapshot = $service->ensureForOrder($order);

        $this->assertSame('Primjer d.o.o.', $snapshot['receiver_name']);
        $this->assertSame('Testna 1', $snapshot['receiver_street']);
        $this->assertSame('10000 Zagreb', $snapshot['receiver_place']);
        $this->assertSame('HR1210010051863000160', $snapshot['receiver_iban']);
        $this->assertStringContainsString('Primatelj uplate:', $snapshot['instructions']);
        foreach (['qr', 'epc_qr'] as $prefix) {
            $binary = base64_decode($snapshot[$prefix.'_image_base64'], true);
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $binary);
            $this->assertNotFalse(getimagesizefromstring($binary));
            $this->assertSame('', $snapshot[$prefix.'_error']);
        }
        $this->assertSame($snapshot, $service->ensureForOrder($order->fresh()));
        Http::assertNothingSent();
    }

    public function test_local_codes_match_amount_and_reference_and_refresh_when_amount_changes(): void
    {
        $this->method();
        $order = $this->order();
        $service = app(BankTransferUpiService::class);
        $first = $service->ensureForOrder($order);
        $hub = explode("\n", app(Hub3BarcodeService::class)->payload($first));
        $this->assertCount(15, $hub);
        $this->assertSame('000000000012345', $hub[2]);
        $this->assertSame('HR00', $hub[10]);
        $this->assertSame($first['reference'], $hub[11]);
        $this->assertStringContainsString("EUR123.45\n", app(EpcQrService::class)->payload($first));

        $order->update(['grand_total' => 142.19]);
        $changed = $service->ensureForOrder($order);
        $this->assertSame($first['reference'], $changed['reference']);
        $this->assertSame(14219, $changed['amount_cents']);
        foreach (['qr', 'epc_qr'] as $prefix) {
            $this->assertNotSame($first[$prefix.'_image_base64'], $changed[$prefix.'_image_base64']);
        }
        $this->assertStringContainsString("EUR142.19\n", app(EpcQrService::class)->payload($changed));
    }

    public function test_existing_nonempty_receiver_identity_remains_frozen_after_settings_change(): void
    {
        $method = $this->method();
        $order = $this->order();
        $service = app(BankTransferUpiService::class);
        $first = $service->ensureForOrder($order);
        $method->update(['settings' => ['upi_receiver_name' => 'New receiver', 'upi_receiver_iban' => 'HR1723600001101234565']]);
        $this->assertSame($first, $service->ensureForOrder($order->fresh()));
    }

    public function test_invalid_payment_data_keeps_instructions_and_never_generates_a_sample_code(): void
    {
        $this->method()->update(['description' => 'Uplatu izvršite prema uputama trgovine.', 'settings' => ['upi_receiver_iban' => 'HR0000000000000000000']]);
        $snapshot = app(BankTransferUpiService::class)->ensureForOrder($this->order());
        $this->assertSame('', $snapshot['qr_image_base64']);
        $this->assertSame('', $snapshot['epc_qr_image_base64']);
        $this->assertSame('Uplatu izvršite prema uputama trgovine.', $snapshot['instructions']);
        Http::assertNothingSent();
    }

    public function test_incomplete_saved_identity_cannot_be_combined_with_a_different_bank_account(): void
    {
        $this->method();
        $order = $this->order(['payload' => ['bank_transfer' => [
            'receiver_iban' => 'HR1723600001101234565', 'receiver_name' => '', 'instructions' => '',
        ]]]);
        $snapshot = app(BankTransferUpiService::class)->ensureForOrder($order);
        $this->assertSame('HR1723600001101234565', $snapshot['receiver_iban']);
        $this->assertSame('', $snapshot['receiver_name']);
        $this->assertSame('', $snapshot['instructions']);
        $this->assertSame('', $snapshot['qr_image_base64']);
        $this->assertSame('', $snapshot['epc_qr_image_base64']);
    }

    public function test_valid_iban_without_recipient_details_does_not_produce_a_hub3_barcode(): void
    {
        $this->method()->update(['description' => '', 'settings' => ['upi_receiver_iban' => 'HR1210010051863000160']]);
        $snapshot = app(BankTransferUpiService::class)->ensureForOrder($this->order());
        $this->assertSame('', $snapshot['qr_image_base64']);
        $this->assertSame('', $snapshot['epc_qr_image_base64']);
    }

    public function test_explicit_different_account_does_not_inherit_receiver_or_instructions_from_description(): void
    {
        $this->method()->update(['settings' => ['upi_receiver_iban' => 'HR1723600001101234565']]);
        $snapshot = app(BankTransferUpiService::class)->ensureForOrder($this->order());
        $this->assertSame('HR1723600001101234565', $snapshot['receiver_iban']);
        $this->assertSame('', $snapshot['receiver_name']);
        $this->assertSame('', $snapshot['instructions']);
        $this->assertSame('', $snapshot['qr_image_base64']);
    }

    public function test_admin_bank_form_prefills_existing_imported_details_without_saving_on_open(): void
    {
        $method = $this->method();
        $original = $method->fresh()->getRawOriginal();
        $admin = User::factory()->create();
        Bouncer::assign('superadmin')->to($admin);
        Livewire::actingAs($admin)->test(ResourceManager::class, ['resource' => 'payment-methods'])
            ->call('edit', $method->id)->assertSet('form.upi_receiver_name', 'Primjer d.o.o.')
            ->assertSet('form.upi_receiver_street', 'Testna 1')->assertSet('form.upi_receiver_place', '10000 Zagreb')
            ->assertSet('form.upi_receiver_iban', 'HR1210010051863000160');
        $this->assertSame($original, $method->fresh()->getRawOriginal());
    }

    public function test_success_and_order_email_use_the_same_snapshot_and_embed_both_images(): void
    {
        $this->method();
        $order = $this->order();
        $snapshot = app(BankTransferUpiService::class)->ensureForOrder($order);
        $this->withSession(['front.checkout.last_order_id' => $order->id])
            ->get(route('checkout.success', ['orderNumber' => $order->order_number]))
            ->assertOk()->assertSee('HUB3 barkod')->assertSee('SEPA QR kod')
            ->assertSee($snapshot['qr_image_base64'], false)->assertSee($snapshot['epc_qr_image_base64'], false);

        app(SystemSettingsService::class)->putMany(['store_email_enabled' => true, 'store_email_orders_to' => '']);
        app(StoreNotificationService::class)->sendOrderNotification($order->fresh());
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertStringContainsString('HUB3 barkod', $email->getHtmlBody());
        $this->assertStringContainsString('SEPA QR kod', $email->getHtmlBody());
        $this->assertStringContainsString('cid:', $email->getHtmlBody());
        $attachments = $email->getAttachments();
        $this->assertCount(2, $attachments);
        $this->assertEqualsCanonicalizing(
            [base64_decode($snapshot['qr_image_base64']), base64_decode($snapshot['epc_qr_image_base64'])],
            array_map(fn ($attachment) => base64_decode($attachment->bodyToString()), $attachments),
        );
        $this->assertSame($snapshot, $order->fresh()->payload['bank_transfer']);
        Http::assertNothingSent();
    }

    public function test_bank_codes_are_also_embedded_in_gift_voucher_purchase_email(): void
    {
        $this->method();
        Mail::to('buyer@example.test')->send(new GiftVoucherPurchaseConfirmation($this->order(['payload' => ['gift_voucher_purchase' => true]])));
        $email = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertCount(2, $email->getAttachments());
        $this->assertStringContainsString('SEPA QR kod', $email->getHtmlBody());
        $this->assertStringContainsString('cid:', $email->getHtmlBody());
    }

    private function method(): PaymentMethod
    {
        return PaymentMethod::create([
            'code' => 'bank_transfer', 'name' => 'Bankovna uplata', 'is_active' => true,
            'description' => 'Primatelj uplate:<br>Primjer d.o.o.<br>Testna 1, Zagreb, 10000<br>Banka<br>IBAN: HR12 1001 0051 8630 0016 0<br>SWIFT: TESTHR2X', 'settings' => [],
        ]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_replace([
            'order_number' => 'LM-BANK-'.uniqid(), 'source' => 'web', 'locale' => 'hr',
            'currency_code' => 'EUR', 'customer_name' => 'Test Kupac', 'customer_email' => 'buyer@example.test',
            'payment_method_code' => 'bank_transfer', 'payment_method_name' => 'Bankovna uplata',
            'billing_address_line_1' => 'Ulica kupca 2', 'billing_postal_code' => '21000', 'billing_city' => 'Split',
            'subtotal' => 123.45, 'grand_total' => 123.45, 'payload' => [], 'placed_at' => now(),
        ], $attributes));
    }
}
