@extends('layouts.guest')
@section('title', 'All set')

@section('card')
    <div class="text-center mt-1">
        <h5 class="text-primary mb-1">You're all set</h5>
        <p class="text-muted fs-13">
            If you ever forget your PIN, this <strong>recovery code</strong> is the only way back in.
            It is shown <strong>once</strong> — write it down or take a screenshot now.
        </p>
        <div class="zi-recovery my-3" id="recovery-code">{{ $recoveryCode }}</div>
        <p class="text-muted fs-12 mb-4">
            Your invoices live only on this phone. Use <strong>Backup</strong> in the menu now and then
            to keep a copy safe.
        </p>
        <a href="{{ route('dashboard') }}" class="btn btn-primary w-100 btn-lg fs-15">I've saved it — start</a>
    </div>
@endsection
