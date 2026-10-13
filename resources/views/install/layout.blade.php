{{--
    The installer's own shell.

    Deliberately does NOT extend layouts.app, and the reason is not style. That layout calls
    @vite, which reads public/build/manifest.json — a file produced by `npm run build` and
    excluded from the repository. On a fresh clone it does not exist, so @vite throws, and the
    very first screen of the software would be a stack trace about a missing manifest.

    Everything referenced here ships in the repository under public/assets: Velzon's compiled
    Bootstrap, its icon font, and the fonts themselves. Nothing is fetched from a CDN either, so
    the installer renders identically on a machine with no internet connection — which is a real
    situation for a shop server, and the exact moment you least want a half-styled page.

    The result is that setup looks like the application it is setting up, using the application's
    own stylesheet, with no build step required first.
--}}
<!DOCTYPE html>
<html lang="en" data-layout-mode="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Setup') · {{ config('app.name', 'Zentro Invoices') }}</title>

    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css">

    <style>
        :root {
            --setup-ink: #0f172a;
            --setup-muted: #64748b;
            --setup-line: #e2e8f0;
            --setup-surface: #ffffff;
            --setup-canvas: #f6f7fb;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--setup-canvas);
            color: var(--setup-ink);
            -webkit-font-smoothing: antialiased;
        }

        .setup-shell {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            min-height: 100vh;
        }

        /* ---- Left rail: brand and progress ---------------------------------- */

        .setup-rail {
            background: linear-gradient(160deg, #111633 0%, #232a7a 55%, #3a44c2 100%);
            color: #eef1f8;
            padding: 2.25rem 1.75rem;
            display: flex;
            flex-direction: column;
        }

        .setup-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2.5rem;
        }

        .setup-brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .22);
            display: grid;
            place-items: center;
            font-size: 1.25rem;
            flex: 0 0 auto;
        }

        .setup-brand-name {
            font-size: 1.02rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .setup-brand-sub {
            font-size: .74rem;
            color: rgba(238, 241, 248, .62);
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .setup-steps {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
        }

        /* The connecting spine. Drawn behind the markers rather than as a border on each
           item, so the last step does not trail a line into empty space. */
        .setup-steps::before {
            content: "";
            position: absolute;
            left: 15px;
            top: 20px;
            bottom: 24px;
            width: 2px;
            background: rgba(255, 255, 255, .14);
        }

        .setup-step {
            position: relative;
            display: flex;
            gap: .85rem;
            padding: 0 0 1.6rem 0;
        }

        .setup-step-mark {
            position: relative;
            z-index: 1;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: .8rem;
            font-weight: 600;
            flex: 0 0 auto;
            background: #33427a;
            border: 2px solid rgba(255, 255, 255, .2);
            color: rgba(238, 241, 248, .55);
        }

        .setup-step.is-done .setup-step-mark {
            background: #0ab39c;
            border-color: #0ab39c;
            color: #fff;
        }

        .setup-step.is-current .setup-step-mark {
            background: #fff;
            border-color: #fff;
            color: #3a44c2;
            box-shadow: 0 0 0 5px rgba(255, 255, 255, .16);
        }

        .setup-step-title {
            font-size: .875rem;
            font-weight: 500;
            color: rgba(238, 241, 248, .6);
            line-height: 1.35;
        }

        .setup-step.is-current .setup-step-title,
        .setup-step.is-done .setup-step-title {
            color: #fff;
        }

        .setup-step-hint {
            font-size: .74rem;
            color: rgba(238, 241, 248, .45);
            margin-top: .1rem;
        }

        .setup-rail-foot {
            margin-top: auto;
            padding-top: 2rem;
            font-size: .72rem;
            color: rgba(238, 241, 248, .45);
            line-height: 1.6;
        }

        /* ---- Right panel: the actual step ----------------------------------- */

        .setup-main {
            padding: 3rem 2.5rem;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow-x: hidden;
        }

        .setup-panel {
            width: 100%;
            max-width: 720px;
        }

        .setup-eyebrow {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #3a44c2;
            margin-bottom: .4rem;
        }

        .setup-title {
            font-size: 1.6rem;
            font-weight: 600;
            letter-spacing: -.015em;
            margin: 0 0 .45rem;
        }

        .setup-lede {
            color: var(--setup-muted);
            font-size: .925rem;
            line-height: 1.65;
            margin-bottom: 1.75rem;
            max-width: 62ch;
        }

        .setup-card {
            background: var(--setup-surface);
            border: 1px solid var(--setup-line);
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        }

        .setup-card + .setup-card { margin-top: 1rem; }
        .setup-card-body { padding: 1.4rem 1.5rem; }

        .setup-card-head {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--setup-line);
            font-weight: 600;
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        /* ---- Requirement / status rows -------------------------------------- */

        .setup-check {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            padding: .7rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: .875rem;
        }

        .setup-check:last-child { border-bottom: 0; }

        .setup-check-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: .72rem;
            flex: 0 0 auto;
            margin-top: .1rem;
        }

        .setup-check.ok    .setup-check-icon { background: #d9f5f0; color: #0a9384; }
        .setup-check.fail  .setup-check-icon { background: #fde2e2; color: #d64545; }

        .setup-check-label { font-weight: 500; }
        .setup-check-why   { color: var(--setup-muted); font-size: .8rem; margin-top: .1rem; }

        .setup-hint {
            display: block;
            margin-top: .35rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .76rem;
            background: #0f172a;
            color: #e2e8f0;
            padding: .45rem .65rem;
            border-radius: 6px;
            overflow-x: auto;
            white-space: pre;
        }

        /* A shell command shown for copying. A <pre> does not wrap, so a long sudo line
           pushes straight out of the card; wrapping rather than scrolling because this text
           exists to be selected and pasted, and a scrollbar hides the end of the line. */
        .cmd {
            white-space: pre-wrap;
            word-break: break-all;
            overflow-wrap: anywhere;
        }

        /* ---- Forms ----------------------------------------------------------- */

        .setup-label {
            font-size: .8rem;
            font-weight: 500;
            margin-bottom: .3rem;
            color: #334155;
        }

        .setup-optional {
            font-weight: 400;
            color: var(--setup-muted);
            font-size: .74rem;
        }

        .form-control, .form-select { font-size: .9rem; }
        .form-control:focus, .form-select:focus {
            border-color: #8f9cd0;
            box-shadow: 0 0 0 .18rem rgba(64, 81, 137, .13);
        }

        .setup-field-note { font-size: .76rem; color: var(--setup-muted); margin-top: .3rem; }

        .setup-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn-setup {
            background: #3a44c2;
            border-color: #3a44c2;
            color: #fff;
            font-weight: 500;
            padding: .58rem 1.4rem;
            font-size: .9rem;
        }

        .btn-setup:hover, .btn-setup:focus { background: #2f37a3; border-color: #2f37a3; color: #fff; }

        /* Usage-profile cards: radio inputs styled as selectable tiles. */
        .setup-choice {
            display: block;
            cursor: pointer;
            padding: 1rem 1.1rem;
            border: 1.5px solid var(--setup-line);
            border-radius: 12px;
            background: var(--setup-surface);
            transition: border-color .15s, box-shadow .15s;
        }
        .setup-choice:hover { border-color: #a5abe8; }
        .btn-check:checked + .setup-choice {
            border-color: #3a44c2;
            box-shadow: 0 0 0 3px rgba(58, 68, 194, .15);
        }
        .btn-check:focus-visible + .setup-choice { outline: 2px solid #3a44c2; outline-offset: 2px; }
        .setup-choice-icon {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(58, 68, 194, .1);
            color: #3a44c2;
            font-size: 1.2rem;
        }
        .setup-choice-cols { color: #3a44c2; font-weight: 500; }

        .setup-recovery {
            font: 600 1.35rem/1.4 ui-monospace, SFMono-Regular, Menlo, monospace;
            letter-spacing: .08em;
            word-break: break-all;
            padding: .9rem 1rem;
            border: 1.5px dashed #a5abe8;
            border-radius: 10px;
            background: rgba(58, 68, 194, .06);
        }
        .btn-setup:disabled { background: #94a3b8; border-color: #94a3b8; }

        .setup-section-title {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--setup-muted);
            margin: 1.75rem 0 .9rem;
            padding-bottom: .5rem;
            border-bottom: 1px solid var(--setup-line);
        }

        .setup-section-title:first-of-type { margin-top: 0; }

        code.setup-inline {
            background: #eef2ff;
            color: #3b4a80;
            padding: .1rem .35rem;
            border-radius: 4px;
            font-size: .84em;
        }

        /* ---- Responsive ------------------------------------------------------ */

        @media (max-width: 991.98px) {
            .setup-shell { grid-template-columns: 1fr; }
            .setup-rail { padding: 1.5rem; }
            .setup-brand { margin-bottom: 1.25rem; }
            .setup-rail-foot { display: none; }

            /* Horizontal scroller on small screens: the vertical spine and the descriptive
               hints do not survive the squeeze, so only the markers and titles remain. */
            .setup-steps { display: flex; gap: 1.5rem; overflow-x: auto; padding-bottom: .25rem; }
            .setup-steps::before { display: none; }
            .setup-step { padding: 0; flex: 0 0 auto; align-items: center; }
            .setup-step-hint { display: none; }
            .setup-main { padding: 1.5rem 1rem; }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --setup-ink: #e2e8f0;
                --setup-muted: #94a3b8;
                --setup-line: #2b3648;
                --setup-surface: #1a2233;
                --setup-canvas: #131a27;
            }

            .setup-check { border-bottom-color: #232d3f; }
            .setup-label { color: #cbd5e1; }
            .setup-eyebrow { color: #94a7e8; }
            code.setup-inline { background: #253055; color: #b9c5f0; }

            .form-control, .form-select {
                background-color: #131a27;
                border-color: #2b3648;
                color: #e2e8f0;
            }

            .form-control:focus, .form-select:focus { background-color: #131a27; color: #e2e8f0; }

            .setup-choice-cols, .setup-choice-icon { color: #a9b1f5; }
            .setup-choice-icon { background: rgba(169, 177, 245, .12); }
            .btn-check:checked + .setup-choice { border-color: #7c84e8; }
        }
    </style>
</head>
<body>
<div class="setup-shell">
    <aside class="setup-rail">
        <div class="setup-brand">
            <div class="setup-brand-mark"><img src="{{ asset('assets/images/zentro-mark-white.png') }}" alt="" width="26" height="26"></div>
            <div>
                <div class="setup-brand-name">{{ config('app.name', 'Zentro Invoices') }}</div>
                <div class="setup-brand-sub">Setup</div>
            </div>
        </div>

        @php
            $steps = [
                1 => ['Server check', 'PHP, extensions, permissions'],
                2 => ['Database', 'Connect and configure'],
                3 => ['Build tables', 'Create the database tables'],
                4 => ['Your business', 'How you will use it'],
                5 => ['Your login', 'The owner account'],
                6 => ['Done', 'Save your recovery code'],
            ];
            $current = $step ?? 1;
        @endphp

        <ol class="setup-steps">
            @foreach ($steps as $number => [$title, $hint])
                <li @class([
                    'setup-step',
                    'is-done' => $number < $current,
                    'is-current' => $number === $current,
                ])>
                    <span class="setup-step-mark">
                        @if ($number < $current)
                            <i class="ri-check-line"></i>
                        @else
                            {{ $number }}
                        @endif
                    </span>
                    <span>
                        <span class="setup-step-title d-block">{{ $title }}</span>
                        <span class="setup-step-hint d-block">{{ $hint }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        <div class="setup-rail-foot">
            Invoices and digital receipts for a shop, a freelancer or a studio.<br>
            Nothing on this screen is sent anywhere — setup runs entirely on this server.
        </div>
    </aside>

    <main class="setup-main">
        <div class="setup-panel">
            @if (session('error'))
                <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                    <i class="ri-error-warning-line mt-1"></i>
                    <div>
                        <div>{{ session('error') }}</div>
                        @if (session('output'))
                            <pre class="setup-hint mt-2 mb-0">{{ session('output') }}</pre>
                        @endif
                    </div>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success d-flex gap-2 align-items-center" role="alert">
                    <i class="ri-check-double-line"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="d-flex gap-2 align-items-center mb-1">
                        <i class="ri-error-warning-line"></i>
                        <strong>Please fix {{ $errors->count() }} {{ Str::plural('field', $errors->count()) }}.</strong>
                    </div>
                    <ul class="mb-0 ps-4 fs-13">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
