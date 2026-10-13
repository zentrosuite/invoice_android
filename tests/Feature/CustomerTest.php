<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_an_empty_state_then_customers(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->get(route('customers.index'))->assertOk()->assertSee('No customers yet');

        Customer::factory()->create(['name' => 'Rasi', 'business_name' => '5 Star Sales and Service']);

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Rasi')
            ->assertSee('5 Star Sales and Service');
    }

    public function test_a_customer_can_be_created(): void
    {
        $response = $this->actingAs($this->owner())->post(route('customers.store'), [
            'name' => ' Rasi ',
            'business_name' => '5 Star Sales and Service',
            'phone' => '+91 96885 27308',
            'address_line1' => 'Karisavayal',
            'state' => 'Tamil Nadu',
            'email' => '',
        ]);

        $customer = Customer::sole();
        $response->assertRedirect(route('customers.show', $customer));

        $this->assertSame('Rasi', $customer->name);
        $this->assertNull($customer->email);
        $this->assertSame('9688527308', $customer->phone_digits);
        $this->assertSame('919688527308', $customer->whatsappNumber());
    }

    public function test_the_same_number_in_another_format_is_a_duplicate(): void
    {
        Customer::factory()->create(['name' => 'Rasi', 'business_name' => null, 'phone' => '96885 27308']);

        $this->actingAs($this->owner())
            ->post(route('customers.store'), ['name' => 'Someone', 'phone' => '+91-9688527308'])
            ->assertSessionHasErrors(['phone' => 'This number is already saved for Rasi.']);

        $this->assertSame(1, Customer::count());
    }

    public function test_editing_keeps_its_own_number_without_tripping_the_duplicate_check(): void
    {
        $customer = Customer::factory()->create(['name' => 'Rasi', 'phone' => '9688527308']);

        $this->actingAs($this->owner())
            ->put(route('customers.update', $customer), ['name' => 'Rasi K', 'phone' => '096885 27308'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.show', $customer));

        $this->assertSame('Rasi K', $customer->refresh()->name);
    }

    public function test_search_matches_name_business_and_phone_in_any_format(): void
    {
        Customer::factory()->create(['name' => 'Rasi', 'business_name' => '5 Star', 'phone' => '+91 96885 27308']);
        Customer::factory()->create(['name' => 'Kumar', 'business_name' => null, 'phone' => '9000000001']);

        $user = $this->actingAs($this->owner());

        foreach (['ras', '5 star', '96885-273', '27308'] as $term) {
            $this->get(route('customers.index', ['q' => $term]))->assertSee('Rasi')->assertDontSee('Kumar');
        }
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Customer::factory()->create(['name' => 'Rasi']);

        $this->actingAs($this->owner())
            ->get(route('customers.index', ['q' => '%']))
            ->assertSee('No customer matches');
    }

    public function test_the_picker_endpoint_returns_json(): void
    {
        Customer::factory()->create(['name' => 'Rasi', 'business_name' => '5 Star', 'city' => 'Karaikudi']);

        $this->actingAs($this->owner())
            ->getJson(route('customers.search', ['q' => 'rasi']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Rasi — 5 Star')
            ->assertJsonPath('0.address', 'Karaikudi');
    }

    public function test_removing_a_customer_is_a_soft_delete(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);
    }

    public function test_guests_cannot_reach_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->getJson(route('customers.search'))->assertUnauthorized();
    }
}
