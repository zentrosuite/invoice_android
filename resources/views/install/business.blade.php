@extends('install.layout')
@section('title', 'Your business')

@section('content')
    <div class="setup-eyebrow">Step 4 of 6</div>
    <h1 class="setup-title">How will you use it?</h1>
    <p class="setup-lede">
        This sets how a new invoice looks — which columns it has and what they are called. You can
        change it any time in Settings, and every invoice can still hold whatever lines you type.
    </p>

    <form method="POST" action="{{ route('install.business.store') }}">
        @csrf

        @php $selected = old('usage_profile', $saved['usage_profile'] ?? null); @endphp

        <div class="row g-3 mb-4" role="radiogroup" aria-label="Usage profile">
            @foreach ($profiles as $profile)
                <div class="col-sm-6">
                    <input type="radio" class="btn-check" name="usage_profile"
                           id="profile-{{ $profile->value }}" value="{{ $profile->value }}"
                           @checked($selected === $profile->value) required>
                    <label class="setup-choice w-100 h-100" for="profile-{{ $profile->value }}">
                        <span class="setup-choice-icon"><i class="{{ $profile->icon() }}"></i></span>
                        <span class="d-block fw-semibold mt-2">{{ $profile->label() }}</span>
                        <span class="d-block fs-13 text-muted mt-1">{{ $profile->description() }}</span>
                        @php $d = $profile->defaults(); @endphp
                        <span class="d-block fs-12 mt-2 setup-choice-cols">
                            {{ $d['itemLabel'] }}@if ($d['showQuantity']) · {{ $d['quantityLabel'] }}@endif · {{ $d['rateLabel'] }}@if ($d['showQuantity']) · Amount @endif
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('usage_profile')<div class="text-danger fs-13 mb-3">{{ $message }}</div>@enderror

        <div class="setup-card">
            <div class="setup-card-head"><span>Business</span></div>
            <div class="setup-card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="setup-label" for="business_name">Business name</label>
                        <input type="text" id="business_name" name="business_name" maxlength="150" required
                               class="form-control @error('business_name') is-invalid @enderror"
                               value="{{ old('business_name', $saved['business_name'] ?? '') }}"
                               placeholder="e.g. Zentro Studio">
                        <div class="setup-field-note">Printed at the top of every invoice.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="setup-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
                        <input type="tel" id="phone" name="phone" maxlength="30" inputmode="tel"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $saved['phone'] ?? '') }}">
                    </div>
                </div>
                <div class="setup-field-note mt-3">
                    <i class="ri-information-line me-1"></i>
                    Address, logo and your UPI ID for payment QR codes are filled in on the Company
                    profile page after setup.
                </div>
            </div>
        </div>

        <div class="setup-actions">
            <a href="{{ route('install.migrate') }}" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line me-1"></i>Back
            </a>
            <button type="submit" class="btn btn-setup">
                Continue<i class="ri-arrow-right-line ms-1"></i>
            </button>
        </div>
    </form>
@endsection
