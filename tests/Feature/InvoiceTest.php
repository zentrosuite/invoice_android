<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inclusion;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Settings\InvoiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();
    }

    public function test_the_builder_starts_from_settings(): void
    {
        $settings = app(InvoiceSettings::class);
        $settings->template = 'package';
        $settings->numberPrefix = '5S-';
        $settings->nextNumber = 7;
        $settings->numberPadding = 3;
        $settings->defaultTerms = 'Payment on completion';
        $settings->save();

        $this->actingAs($this->user)->get(route('invoices.create'))
            ->assertOk()
            ->assertSee('5S-007')
            ->assertSee('Payment on completion')
            // @js() encodes quotes as \u0022.
            ->assertSee('\\u0022mode\\u0022:\\u0022package\\u0022', false);
    }

    public function test_a_package_invoice_like_the_5_star_one(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'RASI', 'business_name' => '5 Star Sales and Service',
            'phone' => '+91 96885 27308', 'address_line1' => 'Karisavayal', 'city' => null, 'state' => 'Tamil Nadu',
        ]);

        $response = $this->actingAs($this->user)->post(route('invoices.store'), $this->package([
            'customer_id' => $customer->id,
            'status' => 'sent',
            'callout' => ['title' => 'Annual Domain Renewal · thefivestar.in', 'amount' => '899', 'suffix' => '/ year', 'meta' => 'Renews on 25/09/2027'],
        ]));

        $invoice = Invoice::sole();
        $response->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame(PricingMode::Package, $invoice->pricing_mode);
        $this->assertSame('INV-0001', $invoice->number);
        $this->assertSame('3599.00', $invoice->total);
        $this->assertSame('5 Star Sales and Service', $invoice->customer_business);
        $this->assertSame("Karisavayal\nTamil Nadu", $invoice->customer_address);
        $this->assertSame('899', $invoice->callout['amount']);
        $this->assertNotNull($invoice->sent_at);

        $items = $invoice->items;
        $this->assertCount(3, $items);
        $this->assertSame(['Responsive for Desktop and Mobile', 'Contact Form'], $items[1]->detailLines());
        $this->assertSame(Inclusion::Excluded, $items[2]->inclusion);
        $this->assertSame(2, app(InvoiceSettings::class)->nextNumber, 'The series advanced.');

        $this->actingAs($this->user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Three Thousand Five Hundred Ninety-Nine Rupees Only')
            ->assertSee('Not included')
            ->assertSee('₹3,599.00');
    }

    public function test_an_itemised_shop_bill_with_discount_and_tax(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised([
            'items' => [
                ['description' => 'Rice 25kg', 'quantity' => '2', 'rate' => '1,250'],
                ['description' => 'Oil 1L', 'quantity' => '3', 'rate' => '180'],
                ['description' => '', 'quantity' => '9', 'rate' => '9'], // blank row is dropped
            ],
            'discount_type' => 'amount',
            'discount_value' => '40',
            'tax_percent' => '5',
        ]))->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status, 'Save draft is the default.');
        $this->assertSame('3040.00', $invoice->subtotal);
        $this->assertSame('40.00', $invoice->discount_amount);
        $this->assertSame('150.00', $invoice->tax_amount);
        $this->assertSame('3150.00', $invoice->total);
        $this->assertSame(['2500.00', '540.00'], $invoice->items->pluck('amount')->all());
        $this->assertNull($invoice->customer_id);
        $this->assertSame('Cash customer', $invoice->customer_name);
    }

    public function test_the_server_ignores_totals_the_browser_might_send(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised([
            'total' => '1', 'subtotal' => '1',
            'items' => [['description' => 'Thing', 'quantity' => '1', 'rate' => '500']],
        ]));

        $this->assertSame('500.00', Invoice::sole()->total);
    }

    public function test_at_least_one_line_is_required(): void
    {
        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->itemised(['items' => [['description' => '  ']]]))
            ->assertSessionHasErrors('items');
    }

    public function test_a_package_needs_a_price(): void
    {
        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->package(['package_total' => '']))
            ->assertSessionHasErrors('package_total');
    }

    public function test_a_hand_typed_number_is_kept_and_must_be_unique(): void
    {
        Invoice::factory()->create(['number' => 'OLD-100']);

        $this->actingAs($this->user)
            ->post(route('invoices.store'), $this->itemised(['number' => 'OLD-100']))
            ->assertSessionHasErrors(['number' => 'Another invoice already has this number.']);

        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised(['number' => 'OLD-101']));
        $this->assertTrue(Invoice::query()->where('number', 'OLD-101')->exists());
        $this->assertSame(1, app(InvoiceSettings::class)->nextNumber, 'A hand-typed number does not use up the series.');
    }

    public function test_numbering_skips_numbers_already_taken(): void
    {
        Invoice::factory()->create(['number' => 'INV-0001']);

        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised());

        $this->assertTrue(Invoice::query()->where('number', 'INV-0002')->exists());
    }

    public function test_editing_replaces_lines_and_keeps_the_number(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised());
        $invoice = Invoice::sole();

        $this->actingAs($this->user)->get(route('invoices.edit', $invoice))->assertOk()->assertSee($invoice->number);

        $this->actingAs($this->user)
            ->put(route('invoices.update', $invoice), $this->itemised([
                'number' => $invoice->number,
                'items' => [['description' => 'Replaced', 'quantity' => '1', 'rate' => '75']],
            ]))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(['Replaced'], $invoice->items->pluck('description')->all());
        $this->assertSame('75.00', $invoice->total);
        $this->assertSame('INV-0001', $invoice->number);
    }

    public function test_a_sent_invoice_keeps_its_copy_of_the_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Rasi', 'city' => 'Karaikudi']);
        $this->actingAs($this->user)->post(route('invoices.store'), $this->itemised(['customer_id' => $customer->id]));

        $customer->update(['city' => 'Chennai', 'name' => 'Rasi K']);

        $invoice = Invoice::sole();
        $this->assertSame('Rasi', $invoice->customer_name);
        $this->assertSame('Karaikudi', $invoice->customer_address);
    }

    public function test_status_changes(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)->put(route('invoices.status', $invoice), ['status' => 'sent']);
        $this->assertSame(InvoiceStatus::Sent, $invoice->refresh()->status);

        $this->actingAs($this->user)->put(route('invoices.status', $invoice), ['status' => 'paid']);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->actingAs($this->user)->put(route('invoices.status', $invoice), ['status' => 'sent']);
        $this->assertNull($invoice->refresh()->paid_at, 'Marking unpaid clears the paid date.');
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $sent = Invoice::factory()->status(InvoiceStatus::Sent)->create();
        $draft = Invoice::factory()->create();

        $this->actingAs($this->user)->delete(route('invoices.destroy', $sent))->assertSessionHas('error');
        $this->assertModelExists($sent);

        $this->actingAs($this->user)->delete(route('invoices.destroy', $draft))->assertRedirect(route('invoices.index'));
        $this->assertModelMissing($draft);
    }

    public function test_duplicating_makes_a_new_draft_with_the_next_number(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->package(['status' => 'sent']));
        $original = Invoice::sole();
        $original->update(['invoice_date' => now()->subMonth()]);

        $this->actingAs($this->user)->post(route('invoices.duplicate', $original))->assertRedirect();

        $copy = Invoice::query()->whereKeyNot($original->id)->sole();
        $this->assertSame('INV-0002', $copy->number);
        $this->assertSame(InvoiceStatus::Draft, $copy->status);
        $this->assertTrue($copy->invoice_date->isToday());
        $this->assertSame($original->items->pluck('description')->all(), $copy->items->pluck('description')->all());
        $this->assertSame('3599.00', $copy->total);
    }

    public function test_the_list_filters_by_status_and_searches(): void
    {
        Invoice::factory()->create(['number' => 'INV-0001', 'customer_name' => 'Rasi']);
        Invoice::factory()->status(InvoiceStatus::Paid)->create(['number' => 'INV-0002', 'customer_name' => 'Kumar']);

        $this->actingAs($this->user)->get(route('invoices.index', ['status' => 'paid']))
            ->assertSee('INV-0002')->assertDontSee('INV-0001');

        $this->actingAs($this->user)->get(route('invoices.index', ['q' => 'rasi']))
            ->assertSee('INV-0001')->assertDontSee('INV-0002');
    }

    public function test_a_customer_can_be_added_from_the_builder(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('customers.quick'), ['name' => 'Anitha', 'phone' => '9000000002'])
            ->assertCreated()
            ->assertJsonPath('label', 'Anitha');

        $this->actingAs($this->user)
            ->postJson(route('customers.quick'), ['name' => 'Twin', 'phone' => '+91 90000 00002'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function package(array $overrides = []): array
    {
        return array_merge([
            'pricing_mode' => 'package',
            'template' => 'package',
            'invoice_date' => now()->toDateString(),
            'payment_terms' => 'On completion',
            'package_total' => '3,599',
            'items' => [
                ['description' => 'Professional Logo Design', 'inclusion' => 'included'],
                ['description' => 'Professional Website', 'details' => "Responsive for Desktop and Mobile\n\nContact Form", 'inclusion' => 'included'],
                ['description' => 'Paid ads', 'inclusion' => 'excluded'],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemised(array $overrides = []): array
    {
        return array_merge([
            'pricing_mode' => 'itemised',
            'template' => 'classic',
            'invoice_date' => now()->toDateString(),
            'show_quantity' => '1',
            'walkin_name' => 'Cash customer',
            'items' => [['description' => 'Thing', 'quantity' => '1', 'rate' => '100']],
        ], $overrides);
    }
}
