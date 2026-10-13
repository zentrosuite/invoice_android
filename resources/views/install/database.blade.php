@extends('install.layout')
@section('title', 'Database')

@section('content')
    <div class="setup-eyebrow">Step 2 of 6</div>
    <h1 class="setup-title">Point it at a database</h1>
    <p class="setup-lede">
        This application stores everything in MySQL — stock, money and every document, all of it
        append-only, so the database is the record. Enter the connection details below and they
        will be tested before anything is written.
    </p>

    <form method="POST" action="{{ route('install.database.store') }}" autocomplete="off">
        @csrf

        <div class="setup-card">
            <div class="setup-card-head"><span>Connection</span></div>
            <div class="setup-card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="setup-label" for="host">Host</label>
                        <input type="text" id="host" name="host" required
                               class="form-control @error('host') is-invalid @enderror"
                               value="{{ old('host', $config['host']) }}" placeholder="127.0.0.1">
                        <div class="setup-field-note">
                            Use <code class="setup-inline">127.0.0.1</code> rather than
                            <code class="setup-inline">localhost</code> if you want TCP instead of a socket.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="setup-label" for="port">Port</label>
                        <input type="number" id="port" name="port" required min="1" max="65535"
                               class="form-control @error('port') is-invalid @enderror"
                               value="{{ old('port', $config['port']) }}">
                    </div>

                    <div class="col-md-12">
                        <label class="setup-label" for="database">Database name</label>
                        <input type="text" id="database" name="database" required
                               class="form-control @error('database') is-invalid @enderror"
                               value="{{ old('database', $config['database']) }}"
                               pattern="[A-Za-z0-9_]+"
                               placeholder="zentro_invoices">
                        <div class="setup-field-note">Letters, numbers and underscores only.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="setup-label" for="username">MySQL username</label>
                        <input type="text" id="username" name="username" required
                               class="form-control @error('username') is-invalid @enderror"
                               value="{{ old('username', $config['username']) }}"
                               autocomplete="off">
                    </div>

                    <div class="col-md-6">
                        <label class="setup-label" for="password">
                            MySQL password <span class="setup-optional">— blank if there is none</span>
                        </label>
                        <div class="input-group">
                            <input type="password" id="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   value="{{ old('password', $config['password']) }}"
                                   autocomplete="new-password">
                            <button class="btn btn-light" type="button" data-toggle-password="#password"
                                    aria-label="Show password">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="create_database"
                           name="create_database" {{ old('create_database', '1') ? 'checked' : '' }}>
                    <label class="form-check-label fs-13" for="create_database">
                        Create the database if it does not exist
                        <div class="setup-field-note">
                            Needs a MySQL user with the CREATE privilege. If yours does not have it,
                            untick this and run the statement below yourself first.
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Shown always, not only on failure. Plenty of hosted MySQL accounts cannot create a
             database at all, and finding that out only after a failed attempt wastes a step. --}}
        <div class="setup-card">
            <div class="setup-card-head">
                <span>Prefer to create it yourself?</span>
                <button type="button" class="btn btn-sm btn-light" data-copy-target="#create-sql">
                    <i class="ri-file-copy-line me-1"></i>Copy
                </button>
            </div>
            <div class="setup-card-body">
                <p class="fs-13 text-muted mb-2">
                    Run this in <code class="setup-inline">mysql</code>, phpMyAdmin, or your host's
                    control panel. The character set is not optional — the wrong one fails much
                    later, on the first product name containing a ₹.
                </p>
                <pre class="setup-hint mb-0" id="create-sql">{{ $createStatement }}</pre>
            </div>
        </div>

        <div class="setup-actions">
            <a href="{{ route('install.requirements') }}" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line me-1"></i>Back
            </a>
            <button type="submit" class="btn btn-setup">
                Test connection and continue<i class="ri-arrow-right-line ms-1"></i>
            </button>
        </div>
    </form>

    <script>
        // Two small conveniences, written in plain DOM because the installer has no bundled
        // JavaScript — resources/js is built by Vite, and on a fresh clone that has not run.
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.dataset.togglePassword);
                if (!input) return;
                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                button.querySelector('i').className = hidden ? 'ri-eye-off-line' : 'ri-eye-line';
            });
        });

        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var source = document.querySelector(button.dataset.copyTarget);
                if (!source) return;

                var done = function () {
                    var original = button.innerHTML;
                    button.innerHTML = '<i class="ri-check-line me-1"></i>Copied';
                    setTimeout(function () { button.innerHTML = original; }, 1600);
                };

                // navigator.clipboard is unavailable over plain HTTP, which is exactly how a
                // local install is usually reached. Fall back rather than silently doing nothing.
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(source.textContent).then(done);
                    return;
                }

                var scratch = document.createElement('textarea');
                scratch.value = source.textContent;
                scratch.style.position = 'fixed';
                scratch.style.opacity = '0';
                document.body.appendChild(scratch);
                scratch.select();
                try { document.execCommand('copy'); done(); } catch (e) { /* nothing to do */ }
                document.body.removeChild(scratch);
            });
        });
    </script>
@endsection
