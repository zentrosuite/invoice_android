@extends('layouts.guest')
@section('title', 'Sign in')

@section('card')
    <div class="text-center mt-2">
        <h5 class="text-primary">Welcome back</h5>
        <p class="text-muted">Sign in to {{ app(\App\Settings\CompanySettings::class)->name ?: config('app.name') }}.</p>
    </div>

    @include('components.flash')

    <div class="p-2 mt-3">
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="float-end">
                    <a href="{{ route('recover') }}" class="text-muted">Forgot password?</a>
                </div>
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Keep me signed in</label>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary w-100 btn-lg fs-15" type="submit">Sign in</button>
            </div>
        </form>
    </div>
@endsection
