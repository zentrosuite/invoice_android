<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UsageProfile;
use App\Settings\InvoiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders(): void
    {
        $this->actingAs($this->owner())->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Usage profile')
            ->assertSee('Next invoice');
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->owner())
            ->put(route('settings.update'), $this->valid([
                'number_prefix' => '5S/',
                'next_number' => '42',
                'number_padding' => '3',
                'default_tax_percent' => '18',
                'show_quantity' => '0',
                'default_terms' => "  Line one\nLine two  ",
                'due_days' => '',
            ]))
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasNoErrors();

        $settings = app(InvoiceSettings::class);
        $this->assertSame('5S/042', $settings->previewNumber());
        $this->assertSame(18.0, $settings->defaultTaxPercent);
        $this->assertFalse($settings->showQuantity);
        $this->assertSame("Line one\nLine two", $settings->defaultTerms);
        $this->assertNull($settings->dueDays);
    }

    public function test_a_blank_tax_means_no_tax(): void
    {
        $this->actingAs($this->owner())->put(route('settings.update'), $this->valid(['default_tax_percent' => '']));

        $this->assertNull(app(InvoiceSettings::class)->defaultTaxPercent);
    }

    public function test_an_unknown_template_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->put(route('settings.update'), $this->valid(['template' => 'fancy']))
            ->assertSessionHasErrors('template');
    }

    public function test_switching_profile_resets_columns_but_keeps_numbering(): void
    {
        $settings = app(InvoiceSettings::class);
        $settings->nextNumber = 77;
        $settings->itemLabel = 'Custom';
        $settings->save();

        $this->actingAs($this->owner())
            ->put(route('settings.profile'), ['usage_profile' => 'studio'])
            ->assertRedirect(route('settings.edit'));

        $settings = app(InvoiceSettings::class);
        $settings->refresh();
        $this->assertSame(UsageProfile::Studio, $settings->profile());
        $this->assertSame('Hours', $settings->quantityLabel);
        $this->assertSame('Service', $settings->itemLabel);
        $this->assertSame(77, $settings->nextNumber);
    }

    public function test_the_whatsapp_app_and_caption_are_saved(): void
    {
        $this->actingAs($this->owner())
            ->put(route('settings.update'), $this->valid(['whatsapp_app' => 'personal', 'whatsapp_message' => "Hello {customer}\r\nInvoice {number}"]))
            ->assertSessionHasNoErrors();

        $settings = app(InvoiceSettings::class);
        $this->assertSame('personal', $settings->whatsappApp);
        $this->assertSame("Hello {customer}\nInvoice {number}", $settings->whatsappMessage);
    }

    public function test_freelancers_and_studios_start_on_the_package_design(): void
    {
        foreach ([UsageProfile::Freelancer, UsageProfile::Studio] as $profile) {
            $settings = app(InvoiceSettings::class);
            $settings->applyProfile($profile);

            $this->assertTrue($settings->templateEnum()->isPackage(), $profile->label());
        }

        $settings->applyProfile(UsageProfile::Shop);
        $this->assertFalse($settings->templateEnum()->isPackage());
    }

    public function test_the_package_design_can_be_chosen(): void
    {
        $this->actingAs($this->owner())
            ->put(route('settings.update'), $this->valid(['template' => 'package']))
            ->assertSessionHasNoErrors();

        $this->assertSame('package', app(InvoiceSettings::class)->template);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'item_label' => 'Item',
            'show_quantity' => '1',
            'quantity_label' => 'Qty',
            'rate_label' => 'Rate',
            'template' => 'classic',
            'number_prefix' => 'INV-',
            'next_number' => '1',
            'number_padding' => '4',
            'currency_symbol' => '₹',
            'footer_note' => 'Thank you',
            'whatsapp_message' => 'Hi {customer}, invoice {number}',
            'whatsapp_app' => 'business',
        ], $overrides);
    }
}
