<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\InvoiceTemplate;
use App\Enums\UsageProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\InvoiceSettingsRequest;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * How a new invoice starts: usage profile, columns, design, numbering and default wording.
 *
 * Changes apply to invoices created afterwards. An invoice keeps the columns it was made with,
 * so switching profile never rewrites a bill a customer already has.
 */
final class InvoiceSettingsController extends Controller
{
    public function edit(InvoiceSettings $settings): View
    {
        return view('settings.invoice', [
            'settings' => $settings,
            'profiles' => UsageProfile::cases(),
            'templates' => InvoiceTemplate::cases(),
        ]);
    }

    public function update(InvoiceSettingsRequest $request, InvoiceSettings $settings): RedirectResponse
    {
        $data = $request->validated();
        $text = static fn (?string $value): ?string => ($value === null || trim($value) === '') ? null : trim($value);

        $settings->itemLabel = trim($data['item_label']);
        $settings->showQuantity = $request->boolean('show_quantity');
        $settings->quantityLabel = trim($data['quantity_label']);
        $settings->rateLabel = trim($data['rate_label']);
        $settings->template = $data['template'];
        $settings->numberPrefix = trim((string) ($data['number_prefix'] ?? ''));
        $settings->nextNumber = (int) $data['next_number'];
        $settings->numberPadding = (int) $data['number_padding'];
        $settings->defaultTaxPercent = isset($data['default_tax_percent']) && $data['default_tax_percent'] !== ''
            ? round((float) $data['default_tax_percent'], 2)
            : null;
        $settings->currencySymbol = trim($data['currency_symbol']);
        $settings->dueDays = isset($data['due_days']) && $data['due_days'] !== '' ? (int) $data['due_days'] : null;
        $settings->paymentTerms = $text($data['payment_terms'] ?? null);
        $settings->defaultTerms = $text($data['default_terms'] ?? null);
        $settings->defaultNotes = $text($data['default_notes'] ?? null);
        $settings->footerNote = $text($data['footer_note'] ?? null);
        $settings->whatsappMessage = trim(str_replace("\r\n", "\n", $data['whatsapp_message']));
        $settings->whatsappApp = $data['whatsapp_app'];
        $settings->save();

        return redirect()->route('settings.edit')->with('success', 'Invoice settings saved.');
    }

    /**
     * Switch usage profile, resetting columns and design to that profile's defaults.
     */
    public function profile(Request $request, InvoiceSettings $settings): RedirectResponse
    {
        $data = $request->validate(['usage_profile' => ['required', Rule::enum(UsageProfile::class)]]);

        $profile = UsageProfile::from($data['usage_profile']);
        $settings->applyProfile($profile);
        $settings->save();

        return redirect()->route('settings.edit')
            ->with('success', "Switched to {$profile->label()}. Columns and design were reset to its defaults.");
    }
}
