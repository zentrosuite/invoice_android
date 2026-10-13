@extends('install.layout')
@section('title', 'Server check')

@section('content')
    <div class="setup-eyebrow">Step 1 of 6</div>
    <h1 class="setup-title">Let's check this server can run it</h1>
    <p class="setup-lede">
        Everything below is something the application genuinely uses. If any of it is missing,
        it is better to know now than to find out halfway through your first invoice — so each
        failure comes with the command that fixes it.
    </p>

    @if ($degraded ?? false)
        {{-- Running from memory because the filesystem is not writable. Said plainly, because
             otherwise this page looks like a healthy install that merely has a warning. --}}
        <div class="alert alert-danger" role="alert">
            <div class="d-flex gap-2 align-items-start">
                <i class="ri-lock-line fs-18 mt-1"></i>
                <div>
                    <strong>The web server cannot write to this folder yet.</strong>
                    <div class="fs-13 mt-1">
                        This page is running from memory so it could load at all — nothing is being
                        saved, and setup cannot continue past this step. A fresh clone belongs to
                        whoever cloned it, and the web server is a different user, so it needs to be
                        given access. That needs root, which is why it cannot be done from here.
                    </div>
                    <div class="fs-13 mt-2">Run this once, then reload:</div>
                    <pre class="cmd bg-dark text-light border rounded p-2 mt-2 mb-0 fs-12"><code>{{ $fixCommand }}</code></pre>
                </div>
            </div>
        </div>
    @endif

    @if ($checks['satisfied'])
        <div class="alert alert-success d-flex gap-2 align-items-center" role="alert">
            <i class="ri-shield-check-line fs-18"></i>
            <div>
                Everything checks out. This server is ready.
                @if (! empty($detectedUrl))
                    <div class="fs-13 mt-1 text-body">
                        Detected address: <code>{{ $detectedUrl }}</code> — saved as
                        <code>APP_URL</code>, so links in emails and PDFs point back here. Change
                        it in <code>.env</code> later if you put a real domain in front.
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
            <i class="ri-alert-line fs-18 mt-1"></i>
            <div>
                <strong>{{ $checks['failureCount'] }}
                    {{ Str::plural('thing', $checks['failureCount']) }} needs attention.</strong>
                <div class="fs-13 mt-1">
                    Fix them, then reload this page. Nothing has been changed on your server yet.
                </div>
            </div>
        </div>
    @endif

    <div class="setup-card">
        <div class="setup-card-head">
            <span>PHP</span>
            <span class="badge {{ $checks['php']['ok'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                {{ $checks['php']['current'] }}
            </span>
        </div>
        <div @class(['setup-check', 'ok' => $checks['php']['ok'], 'fail' => ! $checks['php']['ok']])>
            <span class="setup-check-icon">
                <i class="{{ $checks['php']['ok'] ? 'ri-check-line' : 'ri-close-line' }}"></i>
            </span>
            <span class="flex-grow-1">
                <span class="setup-check-label">{{ $checks['php']['label'] }}</span>
                @unless ($checks['php']['ok'])
                    <code class="setup-hint">{{ $checks['php']['hint'] }}</code>
                @endunless
            </span>
        </div>
    </div>

    <div class="setup-card">
        <div class="setup-card-head">
            <span>PHP extensions</span>
            @php $extOk = count(array_filter($checks['extensions'], fn ($e) => $e['ok'])); @endphp
            <span class="badge bg-light text-body">{{ $extOk }} / {{ count($checks['extensions']) }}</span>
        </div>
        @foreach ($checks['extensions'] as $extension)
            <div @class(['setup-check', 'ok' => $extension['ok'], 'fail' => ! $extension['ok']])>
                <span class="setup-check-icon">
                    <i class="{{ $extension['ok'] ? 'ri-check-line' : 'ri-close-line' }}"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="setup-check-label">{{ $extension['label'] }}</span>
                    <span class="setup-check-why">{{ $extension['why'] }}</span>
                    @if (! $extension['ok'] && isset($extension['hint']))
                        <code class="setup-hint">{{ $extension['hint'] }}</code>
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    <div class="setup-card">
        <div class="setup-card-head"><span>Folder permissions</span></div>
        @foreach ($checks['paths'] as $path)
            <div @class(['setup-check', 'ok' => $path['ok'], 'fail' => ! $path['ok']])>
                <span class="setup-check-icon">
                    <i class="{{ $path['ok'] ? 'ri-check-line' : 'ri-close-line' }}"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="setup-check-label"><code class="setup-inline">{{ $path['label'] }}</code></span>
                    <span class="setup-check-why">{{ $path['why'] }}</span>
                    @unless ($path['ok'])
                        <code class="setup-hint">{{ $path['hint'] }}</code>
                    @endunless
                </span>
            </div>
        @endforeach
    </div>

    <div class="setup-actions">
        <a href="{{ route('install.requirements') }}" class="btn btn-light btn-sm">
            <i class="ri-refresh-line me-1"></i>Re-check
        </a>

        @if ($checks['satisfied'])
            <a href="{{ route('install.database') }}" class="btn btn-setup">
                Continue<i class="ri-arrow-right-line ms-1"></i>
            </a>
        @else
            {{-- Disabled rather than hidden. A button that vanishes reads as a broken page;
                 one that is visibly unavailable reads as a step not yet finished. --}}
            <button class="btn btn-setup" disabled title="Fix the items above first">
                Continue<i class="ri-arrow-right-line ms-1"></i>
            </button>
        @endif
    </div>
@endsection
