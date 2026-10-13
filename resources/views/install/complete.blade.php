@extends('install.layout')
@section('title', 'Done')

@section('content')
    <div class="setup-eyebrow">Step 6 of 6</div>
    <h1 class="setup-title">You're all set</h1>

    @if ($recoveryCode)
        <p class="setup-lede">
            One last thing. This installation does not send email, so if you ever forget your
            password, this <strong>recovery code</strong> is how you get back in. It is shown
            <strong>only once</strong> — write it down or save it in a password manager now.
        </p>

        <div class="setup-card">
            <div class="setup-card-head"><span>Recovery code</span></div>
            <div class="setup-card-body text-center">
                <div class="setup-recovery" id="recovery-code">{{ $recoveryCode }}</div>
                <button type="button" class="btn btn-light btn-sm mt-3"
                        onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('recovery-code').textContent.trim()).then(() => this.textContent = 'Copied')">
                    <i class="ri-file-copy-line me-1"></i>Copy
                </button>
                @if ($email)
                    <div class="setup-field-note mt-3">Sign in as <strong>{{ $email }}</strong>.</div>
                @endif
            </div>
        </div>
    @else
        <p class="setup-lede">Setup is complete. Sign in with the email and password you just chose.</p>
    @endif

    <div class="setup-actions">
        <span></span>
        <a href="{{ route('login') }}" class="btn btn-setup">
            @if ($recoveryCode) I've saved it — sign in @else Sign in @endif
            <i class="ri-arrow-right-line ms-1"></i>
        </a>
    </div>
@endsection
