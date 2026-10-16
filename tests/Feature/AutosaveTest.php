<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Autosave;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutosaveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();
    }

    public function test_the_builder_saves_unsaved_work_in_the_background(): void
    {
        $customer = Customer::factory()->create(['name' => 'Fawwash', 'business_name' => null]);

        $this->actingAs($this->user)
            ->postJson(route('invoices.autosave'), ['key' => 'invoice:new', 'form' => $this->form($customer)])
            ->assertOk()
            ->assertJsonStructure(['saved_at']);

        $autosave = Autosave::sole();
        $this->assertSame('invoice:new', $autosave->key);
        $this->assertStringStartsWith('Fawwash · 2 lines', $autosave->label);

        // Saving again replaces it rather than adding a second one.
        $this->actingAs($this->user)->postJson(route('invoices.autosave'), ['key' => 'invoice:new', 'form' => $this->form($customer)]);
        $this->assertSame(1, Autosave::count());
    }

    public function test_unknown_keys_are_refused(): void
    {
        foreach (['invoice:999', 'customer:1', '../x'] as $key) {
            $this->actingAs($this->user)
                ->postJson(route('invoices.autosave'), ['key' => $key, 'form' => 'a=1'])
                ->assertStatus(422);
        }
        $this->assertSame(0, Autosave::count());
    }

    public function test_the_dashboard_and_builder_offer_to_continue(): void
    {
        $this->autosave();

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Unfinished new invoice')->assertSee('Continue');
        $this->actingAs($this->user)->get(route('invoices.create'))
            ->assertSee('You have unsaved work on a new invoice');
    }

    public function test_continue_refills_the_builder(): void
    {
        $this->autosave();

        $this->actingAs($this->user)
            ->get(route('invoices.autosave.restore', ['key' => 'invoice:new']))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasInput('items')
            ->assertSessionHasInput('notes', 'Deliver by Friday');

        // The builder then rebuilds from that input (as after a failed save).
        $this->actingAs($this->user)
            ->withSession(['_old_input' => Autosave::sole()->input()])
            ->get(route('invoices.create'))
            ->assertSee('Cement 50kg')
            ->assertSee('Deliver by Friday')
            ->assertDontSee('You have unsaved work');
    }

    public function test_saving_the_invoice_clears_the_unsaved_work(): void
    {
        $this->autosave();

        $this->actingAs($this->user)->post(route('invoices.store'), Autosave::sole()->input())->assertSessionHasNoErrors();

        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, Autosave::count());
    }

    public function test_unsaved_work_can_be_discarded(): void
    {
        $this->autosave();

        $this->actingAs($this->user)
            ->delete(route('invoices.autosave.destroy'), ['key' => 'invoice:new'])
            ->assertSessionHas('success');

        $this->assertSame(0, Autosave::count());
    }

    public function test_edits_saved_later_make_the_unsaved_copy_stale(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->user)->post(route('invoices.store'), Autosave::make(['payload' => $this->form($customer)])->input() + ['status' => 'sent']);
        $invoice = Invoice::sole();

        $this->actingAs($this->user)->postJson(route('invoices.autosave'), ['key' => 'invoice:' . $invoice->id, 'form' => $this->form($customer)]);
        $this->assertSame(1, Autosave::count());
        $this->actingAs($this->user)->get(route('invoices.edit', $invoice))->assertSee('You have unsaved work on this invoice');

        $this->travel(1)->minutes();
        $invoice->touch();

        $this->actingAs($this->user)->get(route('invoices.edit', $invoice))->assertDontSee('You have unsaved work');
        $this->assertSame(0, Autosave::count());
    }

    public function test_work_on_a_deleted_invoice_is_dropped(): void
    {
        $invoice = Invoice::factory()->create();
        Autosave::create(['key' => 'invoice:' . $invoice->id, 'payload' => 'notes=x', 'label' => 'x']);
        $invoice->delete();

        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee('Unsaved changes to an invoice');
        $this->assertSame(0, Autosave::count());
    }

    private function autosave(): void
    {
        $this->actingAs($this->user)->postJson(route('invoices.autosave'), [
            'key' => 'invoice:new',
            'form' => $this->form(Customer::factory()->create(['name' => 'Fawwash'])),
        ])->assertOk();
    }

    /** The builder's form, URL-encoded as the page sends it. */
    private function form(Customer $customer): string
    {
        return http_build_query([
            '_token' => 'x',
            'pricing_mode' => 'itemised',
            'template' => 'classic',
            'show_quantity' => '1',
            'status' => 'draft',
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Cement 50kg', 'quantity' => '10', 'rate' => '300'],
                ['description' => 'Sand', 'quantity' => '1', 'rate' => '1200'],
                ['description' => '', 'quantity' => '', 'rate' => ''],
            ],
            'notes' => 'Deliver by Friday',
        ]);
    }
}
