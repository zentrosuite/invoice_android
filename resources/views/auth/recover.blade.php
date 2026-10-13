@extends('layouts.guest')
@section('title', 'Forgot PIN')

@section('card')
    <div class="text-center mt-1 mb-3">
        <h5 class="text-primary mb-1">Set a new PIN</h5>
        <p class="text-muted mb-0 fs-13">Use the recovery code you saved when you set up the app.</p>
    </div>

    <form method="POST" action="{{ route('recover.store') }}">
        @csrf
        <div class="mb-3">
            <label for="recovery_code" class="form-label">Recovery code</label>
            <input type="text" id="recovery_code" name="recovery_code" required autocomplete="off"
                   autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX"
                   class="form-control font-monospace @error('recovery_code') is-invalid @enderror">
            @error('recovery_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-pin name="pin" label="New PIN" />
        <div class="mt-3"><x-pin name="pin_confirmation" label="Type it again" /></div>
        <button class="btn btn-primary w-100 btn-lg fs-15 mt-4" type="submit">Set new PIN</button>
    </form>

    <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}" class="fs-13">Back</a></p>
@endsection
