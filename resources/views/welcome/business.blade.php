@extends('layouts.guest')
@section('title', 'Welcome')

@section('card')
    <div class="text-center mt-1 mb-3">
        <h5 class="text-primary mb-1">Welcome to Zentro Invoice</h5>
        <p class="text-muted mb-0 fs-13">Everything stays on this phone. Two quick steps to start.</p>
        <a href="#restore" class="d-inline-block fs-13 mt-1">Have a backup? Restore it instead</a>
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

@section('below-card')
    <div class="card" id="restore" x-data="welcomeRestore(@js(route('welcome.restore')))">
        <div class="card-body p-4">
            <div class="d-flex gap-3 align-items-start">
                <i class="ri-history-line fs-3 text-primary"></i>
                <div>
                    <div class="fw-medium">Moving to a new phone?</div>
                    <p class="text-muted fs-13 mb-3">
                        Restore a <strong>.zentrobackup</strong> file instead of setting up. Your invoices,
                        customers, logo and PIN come back as they were.
                    </p>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary w-100" @click="restore()" :disabled="busy">
                <span x-show="busy" class="spinner-border spinner-border-sm me-1" x-cloak></span>
                <i class="ri-folder-open-line me-1" x-show="!busy"></i>Restore from a backup file
            </button>
            <div class="text-danger fs-13 mt-2" x-show="error" x-text="error" x-cloak></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('welcomeRestore', (url) => ({
                busy: false,
                error: '',
                restore() {
                    this.busy = true;
                    this.error = '';
                    ZentroNative.pickFile(['*/*'])
                        .then((file) => file ? ZentroNative.post(url, { path: file.path }) : null)
                        .then((result) => { if (result && result.redirect) { location.href = result.redirect; } })
                        .catch((e) => { this.error = e.message || 'Something went wrong.'; })
                        .finally(() => { this.busy = false; });
                },
            }));
        });
    </script>
@endpush
