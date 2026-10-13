@extends('layouts.guest')
@section('title', 'Reset password')

@section('card')
    <div class="text-center mt-2">
        <h5 class="text-primary">Reset your password</h5>
        <p class="text-muted mb-0">Use the recovery code you saved during setup.</p>
    </div>

    <div class="p-2 mt-4">
        <form method="POST" action="{{ route('recover.store') }}">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="recovery_code" class="form-label">Recovery code</label>
                <input type="text" id="recovery_code" name="recovery_code" required autocomplete="off"
                       autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX"
                       class="form-control font-monospace @error('recovery_code') is-invalid @enderror">
                @error('recovery_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">New password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password" class="form-control">
            </div>
            <button class="btn btn-primary w-100 btn-lg fs-15" type="submit">Reset password</button>
        </form>
    </div>
@endsection

@section('below-card')
    <p class="text-center mt-3"><a href="{{ route('login') }}" class="fw-medium">Back to sign in</a></p>
@endsection
