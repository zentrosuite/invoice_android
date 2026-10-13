@extends('install.layout')
@section('title', 'Your login')

@section('content')
    <div class="setup-eyebrow">Step 5 of 6</div>
    <h1 class="setup-title">Create your login</h1>
    <p class="setup-lede">
        This is the only account. Everything you create — customers, invoices, drafts — is visible
        once you sign in with it.
    </p>

    <form method="POST" action="{{ route('install.account.store') }}">
        @csrf
        <div class="setup-card">
            <div class="setup-card-head"><span>Owner</span></div>
            <div class="setup-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="setup-label" for="name">Your name</label>
                        <input type="text" id="name" name="name" maxlength="100" required autocomplete="name"
                               class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="setup-label" for="email">Email (your username)</label>
                        <input type="email" id="email" name="email" maxlength="150" required autocomplete="username"
                               class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="setup-label" for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror">
                        <div class="setup-field-note">At least 8 characters.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="setup-label" for="password_confirmation">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required
                               autocomplete="new-password" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="setup-actions">
            <a href="{{ route('install.business') }}" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line me-1"></i>Back
            </a>
            <button type="submit" class="btn btn-setup">
                Finish setup<i class="ri-check-line ms-1"></i>
            </button>
        </div>
    </form>
@endsection
