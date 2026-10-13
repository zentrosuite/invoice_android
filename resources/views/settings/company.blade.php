@extends('layouts.app', ['pageTitle' => 'Company profile'])
@section('title', 'Company profile')

@php
    $field = fn (string $input, ?string $value) => old($input, $value ?? '');
@endphp

@section('content')
    <form method="POST" action="{{ route('company.update') }}" enctype="multipart/form-data"
          x-data="companyPreview(@js([
              'name' => $field('name', $company->name),
              'tagline' => $field('tagline', $company->tagline),
              'email' => $field('email', $company->email),
              'phone' => $field('phone', $company->phone),
              'website' => $field('website', $company->website),
              'address_line1' => $field('address_line1', $company->addressLine1),
              'address_line2' => $field('address_line2', $company->addressLine2),
              'city' => $field('city', $company->city),
              'state' => $field('state', $company->state),
              'pincode' => $field('pincode', $company->pincode),
              'tax_id' => $field('tax_id', $company->taxId),
              'logo' => $company->logoUrl(),
          ]))">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-xl-7 order-2 order-xl-1">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Business</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="name">Business name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" required maxlength="150" x-model="f.name"
                                       class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tagline">Tagline</label>
                                <input type="text" id="tagline" name="tagline" maxlength="150" x-model="f.tagline"
                                       placeholder="e.g. Web Design • Branding • Digital Solutions"
                                       class="form-control @error('tagline') is-invalid @enderror">
                                <div class="form-text">Printed in the invoice footer.</div>
                                @error('tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="logo">Logo</label>
                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <div class="zi-logo-slot" x-show="f.logo" x-cloak>
                                        <img :src="f.logo" alt="Logo">
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 200px">
                                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                                               class="form-control @error('logo') is-invalid @enderror"
                                               @change="pickLogo($event)">
                                        <div class="form-text">PNG, JPG or WebP, up to 4 MB. A transparent PNG looks best.</div>
                                        @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                @if ($company->logoUrl())
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"
                                               @change="f.logo = $event.target.checked ? null : @js($company->logoUrl())">
                                        <label class="form-check-label" for="remove_logo">Remove the current logo</label>
                                    </div>
                                @endif
                            </div>
                            <div class="col-12" x-data="{ sig: @js($company->signatureUrl()) }">
                                <label class="form-label" for="signature">Signature</label>
                                <div class="d-flex gap-3 align-items-center flex-wrap">
                                    <div class="zi-logo-slot" x-show="sig" x-cloak>
                                        <img :src="sig" alt="Signature">
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 200px">
                                        <input type="file" id="signature" name="signature" accept="image/png,image/jpeg,image/webp"
                                               class="form-control @error('signature') is-invalid @enderror"
                                               @change="$event.target.files[0] && (sig = URL.createObjectURL($event.target.files[0]))">
                                        <div class="form-text">
                                            Printed above "Authorised signatory". Without one, invoices leave the signatory
                                            block out. Sign on white paper and photograph it, or use a transparent PNG.
                                        </div>
                                        @error('signature')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                @if ($company->signatureUrl())
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="remove_signature"
                                               @change="sig = $event.target.checked ? null : @js($company->signatureUrl())">
                                        <label class="form-check-label" for="remove_signature">Remove the signature</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Contact</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" maxlength="30" inputmode="tel" x-model="f.phone"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" id="email" name="email" maxlength="150" x-model="f.email"
                                       class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="website">Website</label>
                                <input type="text" id="website" name="website" maxlength="150" inputmode="url" x-model="f.website"
                                       placeholder="example.com" class="form-control @error('website') is-invalid @enderror">
                                @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Address</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Address line 1</label>
                                <input type="text" id="address_line1" name="address_line1" maxlength="150" x-model="f.address_line1"
                                       class="form-control @error('address_line1') is-invalid @enderror">
                                @error('address_line1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Address line 2</label>
                                <input type="text" id="address_line2" name="address_line2" maxlength="150" x-model="f.address_line2"
                                       class="form-control @error('address_line2') is-invalid @enderror">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label" for="city">City</label>
                                <input type="text" id="city" name="city" maxlength="80" x-model="f.city"
                                       class="form-control @error('city') is-invalid @enderror">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="state">State</label>
                                <input type="text" id="state" name="state" maxlength="80" x-model="f.state"
                                       class="form-control @error('state') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="pincode">PIN code</label>
                                <input type="text" id="pincode" name="pincode" maxlength="12" inputmode="numeric" x-model="f.pincode"
                                       class="form-control @error('pincode') is-invalid @enderror">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="tax_id">GSTIN / tax ID</label>
                                <input type="text" id="tax_id" name="tax_id" maxlength="40" x-model="f.tax_id"
                                       class="form-control text-uppercase @error('tax_id') is-invalid @enderror">
                                <div class="form-text">Optional. Printed under your address when filled in.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Payments</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="upi_id">UPI ID</label>
                                <input type="text" id="upi_id" name="upi_id" maxlength="100" autocapitalize="off" spellcheck="false"
                                       value="{{ $field('upi_id', $company->upiId) }}" placeholder="9876543210@ybl"
                                       class="form-control @error('upi_id') is-invalid @enderror">
                                @error('upi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="upi_payee_name">Payee name</label>
                                <input type="text" id="upi_payee_name" name="upi_payee_name" maxlength="50"
                                       value="{{ $field('upi_payee_name', $company->upiPayeeName) }}" placeholder="Same as business name"
                                       class="form-control @error('upi_payee_name') is-invalid @enderror">
                                @error('upi_payee_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="text-muted fs-13 mt-3 mb-0">
                            <i class="ri-qr-code-line me-1"></i>
                            Every unpaid invoice gets a QR code for this UPI ID with the amount due filled in.
                            Without a UPI ID, invoices print without a QR.
                        </p>

                        @if ($testQr)
                            <div class="zi-test-qr mt-3">
                                <img src="{{ $testQr }}" alt="Test UPI QR code" width="132" height="132">
                                <div class="fs-13">
                                    <div class="fw-medium mb-1">Check it works</div>
                                    <div class="text-muted">
                                        Scan this ₹1 test code with any UPI app. It should show
                                        <strong>{{ $company->upiPayeeName ?: $company->name }}</strong>.
                                        You can cancel before paying.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="zi-form-actions">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="ri-save-3-line me-1"></i>Save company profile
                    </button>
                </div>
            </div>

            <div class="col-xl-5 order-1 order-xl-2">
                <div class="zi-sticky">
                    <div class="text-uppercase fs-12 fw-semibold text-muted mb-2">Invoice header preview</div>
                    <div class="zi-header-preview">
                        <div class="zi-hp-bar"></div>
                        <div class="zi-hp-body">
                            <div class="zi-hp-brand">
                                <template x-if="f.logo"><img :src="f.logo" alt=""></template>
                                <div class="min-w-0">
                                    <div class="zi-hp-name" x-text="f.name || 'Your business name'"></div>
                                    <div class="zi-hp-line" x-show="f.phone" x-text="f.phone"></div>
                                    <div class="zi-hp-line" x-show="f.email" x-text="f.email"></div>
                                    <div class="zi-hp-line" x-show="f.website" x-text="f.website.replace(/^https?:\/\//, '')"></div>
                                </div>
                            </div>
                            <div class="zi-hp-title">INVOICE</div>
                        </div>
                        <div class="zi-hp-address" x-show="address() || f.tax_id">
                            <span x-text="address()"></span>
                            <template x-if="f.tax_id"><span> · GSTIN <span x-text="f.tax_id.toUpperCase()"></span></span></template>
                        </div>
                        <div class="zi-hp-foot" x-show="f.tagline">
                            <span x-text="f.name"></span> · <span x-text="f.tagline"></span>
                        </div>
                    </div>
                    <p class="text-muted fs-12 mt-2 mb-0">Exact placement depends on the invoice design.</p>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('companyPreview', (initial) => ({
                f: initial,
                address() {
                    const cityLine = [[this.f.city, this.f.state].filter(Boolean).join(', '), this.f.pincode ? '- ' + this.f.pincode : '']
                        .filter(Boolean).join(' ');
                    return [this.f.address_line1, this.f.address_line2, cityLine].filter(Boolean).join(', ');
                },
                pickLogo(event) {
                    const file = event.target.files[0];
                    if (file) { this.f.logo = URL.createObjectURL(file); }
                },
            }));
        });
    </script>
@endpush
