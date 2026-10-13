@extends('layouts.guest')
@section('title', 'Welcome')

@section('card')
    <div class="text-center mt-1 mb-3">
        <h5 class="text-primary mb-1">Welcome to Zentro Invoice</h5>
        <p class="text-muted mb-0 fs-13">Everything stays on this phone. Two quick steps to start.</p>
    </div>

    <form method="POST" action="{{ route('welcome.business') }}">
        @csrf
        @php $selected = old('usage_profile', $saved['usage_profile'] ?? null); @endphp

        <div class="fw-medium mb-2">How will you use it?</div>
        <div class="d-grid gap-2 mb-1">
            @foreach ($profiles as $profile)
                <div>
                    <input type="radio" class="btn-check" name="usage_profile" id="up-{{ $profile->value }}"
                           value="{{ $profile->value }}" @checked($selected === $profile->value) required>
                    <label class="zi-choice d-flex gap-3 align-items-center" for="up-{{ $profile->value }}">
                        <i class="{{ $profile->icon() }} fs-3 text-primary"></i>
                        <span>
                            <span class="d-block fw-medium">{{ $profile->label() }}</span>
                            <span class="d-block text-muted fs-12">{{ $profile->description() }}</span>
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('usage_profile')<div class="text-danger fs-13">{{ $message }}</div>@enderror

        <div class="mt-3">
            <label class="form-label" for="business_name">Business name</label>
            <input type="text" id="business_name" name="business_name" required maxlength="150"
                   value="{{ old('business_name', $saved['business_name'] ?? '') }}"
                   class="form-control @error('business_name') is-invalid @enderror" placeholder="Printed on every invoice">
            @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mt-3">
            <label class="form-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
            <input type="tel" id="phone" name="phone" maxlength="30" inputmode="tel"
                   value="{{ old('phone', $saved['phone'] ?? '') }}" class="form-control @error('phone') is-invalid @enderror">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg fs-15 mt-4">Continue</button>
    </form>
@endsection
