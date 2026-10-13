@extends('layouts.guest')
@section('title', 'Set a PIN')

@section('card')
    <div class="text-center mt-1 mb-3">
        <h5 class="text-primary mb-1">Set your PIN</h5>
        <p class="text-muted mb-0 fs-13">4 digits, asked when you open the app.</p>
    </div>

    <form method="POST" action="{{ route('welcome.pin.store') }}">
        @csrf
        <x-pin name="pin" label="New PIN" :autofocus="true" />
        <div class="mt-3">
            <x-pin name="pin_confirmation" label="Type it again" />
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg fs-15 mt-4">Set PIN</button>
    </form>

    <p class="text-center mt-3 mb-0"><a href="{{ route('welcome') }}" class="fs-13">Back</a></p>
@endsection
