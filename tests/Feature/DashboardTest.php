<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->owner();
    }

    public function test_an_empty_dashboard_guides_setup(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Nothing outstanding');
    }

    public function test_the_figures_add_up(): void
    {
        Invoice::factory()->status(InvoiceStatus::Sent)->create(['number' => 'A', 'total' => '1000.00', 'due_date' => now()->subDays(5), 'customer_name' => 'Late Larry']);
        $partial = Invoice::factory()->status(InvoiceStatus::Sent)->create(['number' => 'B', 'total' => '3000.00', 'due_date' => now()->addDays(5)]);
        Invoice::factory()->create(['number' => 'C', 'total' => '500.00']); // draft
        $paid = Invoice::factory()->status(InvoiceStatus::Sent)->create(['number' => 'D', 'total' => '700.00']);

        $payments = app(PaymentService::class);
        $payments->record($partial, ['amount' => '1200', 'paid_on' => now()->toDateString(), 'method' => 'upi']);
        $payments->record($paid, ['amount' => '700', 'paid_on' => now()->toDateString(), 'method' => 'cash']);

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        // Outstanding: 1000 + (3000 − 1200) = 2800 across two invoices.
        $response->assertSee('₹2,800')->assertSee('2 invoices unpaid');
        // Overdue: only A.
        $response->assertSee('₹1,000')->assertSee('1 past due date');
        // Received this month: 1200 + 700.
        $response->assertSee('₹1,900');
        // The overdue invoice leads the to-collect list.
        $this->assertLessThan(
            strpos($response->getContent(), '₹1,800.00'),
            strpos($response->getContent(), 'Late Larry'),
        );
    }

    public function test_the_unpaid_filter_includes_part_paid_invoices(): void
    {
        $sent = Invoice::factory()->status(InvoiceStatus::Sent)->create(['number' => 'INV-S']);
        $partial = Invoice::factory()->status(InvoiceStatus::Sent)->create(['number' => 'INV-P', 'total' => '1000.00']);
        app(PaymentService::class)->record($partial, ['amount' => '100', 'paid_on' => now()->toDateString(), 'method' => 'cash']);
        Invoice::factory()->status(InvoiceStatus::Paid)->create(['number' => 'INV-X']);

        $this->actingAs($this->user)->get(route('invoices.index', ['status' => 'sent']))
            ->assertSee('INV-S')->assertSee('INV-P')->assertDontSee('INV-X')
            ->assertSee('₹900.00 due');

        $this->actingAs($this->user)->get(route('invoices.index', ['status' => 'partial']))
            ->assertSee('INV-P')->assertDontSee('INV-S');
    }

    public function test_the_list_exports_as_csv(): void
    {
        Invoice::factory()->create(['number' => 'INV-0001', 'customer_name' => 'Rasi', 'total' => '3599.00']);
        Invoice::factory()->status(InvoiceStatus::Paid)->create(['number' => 'INV-0002', 'customer_name' => 'Kumar']);

        // Written into the app's storage, then offered to Android's "Save as".
        $file = $this->actingAs($this->user)->postJson(route('invoices.export'), ['status' => 'draft'])->assertOk()->json();
        $this->assertSame('text/csv', $file['mime']);
        $csv = (string) file_get_contents($file['path']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('INV-0001,' . now()->toDateString() . ',,Rasi,', $csv);
        $this->assertStringContainsString('3599.00,0.00,3599.00', $csv);
        $this->assertStringNotContainsString('INV-0002', $csv);
    }
}
