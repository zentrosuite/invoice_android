@extends('layouts.guest')
@section('title', 'Unlock')

@section('card')
    <div class="text-center mt-1 mb-3">
        <h5 class="text-primary mb-1">{{ app(\App\Settings\CompanySettings::class)->name ?: 'Zentro Invoice' }}</h5>
        <p class="text-muted mb-0 fs-13">Enter your PIN</p>
    </div>

    @include('components.flash')

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <x-pin name="pin" label="PIN" :autofocus="true" :autosubmit="true" />
        <button type="submit" class="btn btn-primary w-100 btn-lg fs-15 mt-4">Unlock</button>
    </form>

    <p class="text-center mt-3 mb-0"><a href="{{ route('recover') }}" class="fs-13">Forgot PIN?</a></p>
@endsection
