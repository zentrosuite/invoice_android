@extends('install.layout')
@section('title', 'Build tables')

@section('content')
    <div class="setup-eyebrow">Step 3 of 6</div>
    <h1 class="setup-title">Build the tables</h1>
    <p class="setup-lede">
        This creates the tables in <code class="setup-inline">{{ $database }}</code> and the default
        settings your invoices start from.
    </p>

    @isset($warning)
        <div class="alert alert-warning d-flex gap-2 align-items-center" role="alert">
            <i class="ri-alert-line"></i>
            <div>{{ $warning }}</div>
        </div>
    @endisset

    @if ($existingTables > 0 && ! $done)
        {{-- The one genuinely destructive moment in the flow. Migrating into a populated
             database is how somebody loses data they cared about, so it is called out
             specifically rather than left to the general "are you sure". --}}
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
            <i class="ri-error-warning-line fs-18 mt-1"></i>
            <div>
                <strong>This database already has {{ $existingTables }}
                    {{ Str::plural('table', $existingTables) }} in it.</strong>
                <div class="fs-13 mt-1">
                    Migrations will run on top of them. If this is a database in use by something
                    else, go back and choose a different name — this is the last point at which
                    that is easy.
                </div>
            </div>
        </div>
    @endif

    <div class="setup-card">
        <div class="setup-card-head">
            <span>What will be created</span>
            @if ($done)
                <span class="badge bg-success-subtle text-success">
                    <i class="ri-check-line"></i> Done
                </span>
            @endif
        </div>

        @foreach ([
            ['ri-table-line', 'Tables', 'Your login, customers and invoices.'],
            ['ri-settings-3-line', 'Default settings', 'Company profile and invoice numbering, ready to fill in.'],
        ] as [$icon, $label, $why])
            <div @class(['setup-check', 'ok' => $done])>
                <span class="setup-check-icon">
                    <i class="{{ $done ? 'ri-check-line' : $icon }}"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="setup-check-label">{{ $label }}</span>
                    <span class="setup-check-why">{{ $why }}</span>
                </span>
            </div>
        @endforeach
    </div>

    <p class="setup-field-note mt-3">
        <i class="ri-time-line me-1"></i>
        This usually takes a few seconds. Every step is safe to run twice, so if the page times
        out you can simply press the button again.
    </p>

    <form method="POST" action="{{ route('install.migrate.run') }}" id="migrate-form">
        @csrf
        <div class="setup-actions">
            <a href="{{ route('install.database') }}" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line me-1"></i>Change database
            </a>

            @if ($done)
                <a href="{{ route('install.business') }}" class="btn btn-setup">
                    Continue<i class="ri-arrow-right-line ms-1"></i>
                </a>
            @else
                <button type="submit" class="btn btn-setup" id="migrate-button">
                    <span class="migrate-idle">
                        Create the tables<i class="ri-arrow-right-line ms-1"></i>
                    </span>
                    <span class="migrate-busy d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>Working…
                    </span>
                </button>
            @endif
        </div>
    </form>

    <script>
        // Migrations are a single blocking POST, so the only honest feedback is "this is
        // running, do not press it again". Double-submitting would start a second migration
        // against a database the first one is still writing to.
        (function () {
            var form = document.getElementById('migrate-form');
            var button = document.getElementById('migrate-button');
            if (!form || !button) return;

            form.addEventListener('submit', function () {
                button.disabled = true;
                button.querySelector('.migrate-idle').classList.add('d-none');
                button.querySelector('.migrate-busy').classList.remove('d-none');
            });
        })();
    </script>
@endsection
