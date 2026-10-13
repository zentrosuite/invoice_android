<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanyProfileRequest;
use App\Services\CompanyImageStore;
use App\Services\UpiQrService;
use App\Settings\CompanySettings;
use App\Support\DeviceFiles;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
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

    public function update(CompanyProfileRequest $request, CompanySettings $company, CompanyImageStore $images): RedirectResponse
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

        // Signatures are wide and short; 600×200 keeps a scanned one sharp at print size.
        foreach (['logo' => ['logoPath', 800, 300], 'signature' => ['signaturePath', 600, 200]] as $field => [$property, $w, $h]) {
            try {
                $company->{$property} = $this->image($request, $images, $field, $company->{$property}, $w, $h);
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors([$field => $e->getMessage()]);
            }
        }

        $company->save();

        return redirect()->route('company.edit')->with('success', 'Company profile saved.');
    }

    /**
     * The new path for an uploaded image, null when "remove" is ticked, or the current path.
     */
    private function image(CompanyProfileRequest $request, CompanyImageStore $images, string $field, ?string $current, int $maxWidth, int $maxHeight): ?string
    {
        if ($request->hasFile($field)) {
            return $images->store($request->file($field), $current, $field, $maxWidth, $maxHeight);
        }

        // The phone sends the picked picture as a data URL (a JPEG, already at print size)
        // rather than a file upload.
        if ($request->filled($field . '_data')) {
            $data = (string) $request->input($field . '_data');
            $extension = match (true) {
                str_starts_with($data, 'data:image/jpeg;base64,') => 'jpg',
                str_starts_with($data, 'data:image/png;base64,') => 'png',
                default => null,
            };
            $bytes = $extension === null ? false : base64_decode(substr($data, strpos($data, ',') + 1), true);

            if ($bytes === false || @getimagesizefromstring($bytes) === false) {
                throw new RuntimeException('That picture could not be read.');
            }

            // Not the system temp folder: on the phone that is /tmp, which Android does not have.
            $tmp = DeviceFiles::path('upload', $field . '-' . bin2hex(random_bytes(6)) . '.' . $extension);
            file_put_contents($tmp, $bytes);

            try {
                return $images->store(new UploadedFile($tmp, $field . '.' . $extension, null, null, true), $current, $field, $maxWidth, $maxHeight);
            } finally {
                @unlink($tmp);
            }
        }

        if ($request->boolean('remove_' . $field)) {
            $images->delete($current);

            return null;
        }

        return $current;
    }
}
