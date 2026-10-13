@extends('layouts.app', ['pageTitle' => 'My profile'])
@section('title', 'My profile')

@section('content')
    @if (session('recovery_code'))
        <div class="alert alert-warning border-2" role="alert">
            <div class="fw-semibold mb-1"><i class="ri-key-2-line me-1"></i>Your new recovery code — shown only once</div>
            <div class="zi-recovery my-2" id="recovery-code">{{ session('recovery_code') }}</div>
            <div class="fs-13">Save it somewhere safe. It is the only way back in if you forget your password.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Login details</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="name">Name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email (your username)</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required maxlength="150" autocomplete="username">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Change password</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                                   class="form-control @error('current_password', 'password') is-invalid @enderror">
                            @error('current_password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="password">New password</label>
                                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                                       class="form-control @error('password', 'password') is-invalid @enderror">
                                @error('password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="password_confirmation">Confirm</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                       autocomplete="new-password" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Recovery code</h5></div>
                <div class="card-body">
                    <p class="text-muted">
                        This app does not send email, so a recovery code is how you reset a forgotten password.
                        Lost yours? Issue a new one — the old one stops working.
                    </p>
                    <form method="POST" action="{{ route('profile.recovery') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label" for="recovery_current_password">Current password</label>
                            <input type="password" id="recovery_current_password" name="current_password" required
                                   autocomplete="current-password"
                                   class="form-control @error('current_password', 'recovery') is-invalid @enderror">
                            @error('current_password', 'recovery')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-auto">
                            <button type="submit" class="btn btn-soft-primary w-100">Issue a new recovery code</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
