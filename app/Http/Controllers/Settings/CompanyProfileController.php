<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyProfileRequest;
use App\Services\LogoStore;
use App\Services\UpiQrService;
use App\Settings\CompanySettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * The business details printed in every invoice header, and the UPI ID its payment QR pays.
 */
final class CompanyProfileController extends Controller
{
    public function edit(CompanySettings $company, UpiQrService $upi): View
    {
        return view('settings.company', [
            'company' => $company,
            // A ₹1 test code, so the owner can scan it and see their own name come up before a
            // customer ever does. Nothing is charged unless they confirm in the app.
            'testQr' => $upi->forAmount('1', 'TEST', 'Test payment'),
        ]);
    }

    public function update(CompanyProfileRequest $request, CompanySettings $company, LogoStore $logos): RedirectResponse
    {
        $data = $request->validated();

        $company->name = trim($data['name']);

        foreach ([
            'tagline' => 'tagline',
            'email' => 'email',
            'phone' => 'phone',
            'website' => 'website',
            'address_line1' => 'addressLine1',
            'address_line2' => 'addressLine2',
            'city' => 'city',
            'state' => 'state',
            'pincode' => 'pincode',
            'tax_id' => 'taxId',
            'upi_id' => 'upiId',
            'upi_payee_name' => 'upiPayeeName',
        ] as $input => $property) {
            $value = isset($data[$input]) ? trim((string) $data[$input]) : '';
            $company->{$property} = $value === '' ? null : $value;
        }

        if ($request->hasFile('logo')) {
            try {
                $company->logoPath = $logos->store($request->file('logo'), $company->logoPath);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['logo' => $e->getMessage()]);
            }
        } elseif ($request->boolean('remove_logo')) {
            $logos->delete($company->logoPath);
            $company->logoPath = null;
        }

        $company->save();

        return redirect()->route('company.edit')->with('success', 'Company profile saved.');
    }
}
