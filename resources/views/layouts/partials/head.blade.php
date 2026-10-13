<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/apple-touch-icon.png') }}">

    {{-- Applies the saved theme before any stylesheet, so dark mode never flashes light.
         The theme lives in localStorage under zi-theme; ui.js writes it.
         The sidebar's colour is set alongside: Velzon colours it independently of the theme. --}}
    <script>
        (function () {
            try {
                var html = document.documentElement;
                var theme = localStorage.getItem('zi-theme');
                // No choice made yet: follow the phone or computer's own setting.
                if (theme !== 'dark' && theme !== 'light') {
                    theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                html.setAttribute('data-bs-theme', theme);
                html.setAttribute('data-sidebar', theme);

                // Same rule as ui.js, applied early so the sidebar does not jump on load.
                var w = window.innerWidth;
                if (w > 767) {
                    html.setAttribute('data-sidebar-size', w <= 1024 ? 'sm' : (localStorage.getItem('zi-sidebar-size') || 'lg'));
                }
            } catch (e) { /* storage blocked: server defaults */ }
        })();
    </script>

    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/css/custom.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('assets/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css">

    @stack('styles')

    {{-- Our overrides, last so they win. Plain files — there is no build step on this host. --}}
    <link href="{{ asset('assets/app/app.css') }}?v={{ config('app.asset_version') }}" rel="stylesheet" type="text/css">
</head>
