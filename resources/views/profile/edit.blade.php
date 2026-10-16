@extends('layouts.app', ['pageTitle' => 'Security'])
@section('title', 'Security')

@section('content')
    @if (session('recovery_code'))
        <div class="alert alert-warning border-2" role="alert">
            <div class="fw-semibold mb-1"><i class="ri-key-2-line me-1"></i>Your new recovery code — shown only once</div>
            <div class="zi-recovery my-2">{{ session('recovery_code') }}</div>
            <div class="fs-13">Write it down or take a screenshot. It is the only way back in if you forget your PIN.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Change PIN</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.pin') }}">
                        @csrf
                        @method('PUT')
                        <x-pin name="current_pin" label="Current PIN" bag="pin" />
                        <div class="mt-3"><x-pin name="pin" label="New PIN" bag="pin" /></div>
                        <div class="mt-3"><x-pin name="pin_confirmation" label="New PIN again" bag="pin" /></div>
                        <button type="submit" class="btn btn-primary w-100 mt-4">Change PIN</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Recovery code</h5></div>
                <div class="card-body">
                    <p class="text-muted">
                        The recovery code sets a new PIN if you forget yours. Lost it? Issue a new one —
                        the old one stops working.
                    </p>
                    <form method="POST" action="{{ route('profile.recovery') }}">
                        @csrf
                        <x-pin name="current_pin" label="Your PIN" bag="recovery" />
                        <button type="submit" class="btn btn-soft-primary w-100 mt-4">Issue a new recovery code</button>
                    </form>
                    <p class="text-muted fs-12 mt-3 mb-0">
                        @if (\App\Http\Middleware\LockWhenIdle::IDLE_MINUTES)
                            The app locks itself after {{ \App\Http\Middleware\LockWhenIdle::IDLE_MINUTES }} minutes without use.
                        @else
                            The app stays open until you tap <strong>Lock</strong> in the menu.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
