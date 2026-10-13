<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>

@stack('scripts')

<script src="{{ asset('assets/app/native.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('assets/app/ui.js') }}?v={{ config('app.asset_version') }}"></script>
<script src="{{ asset('assets/app/app.js') }}?v={{ config('app.asset_version') }}"></script>
{{-- Alpine last and deferred: components registered on `alpine:init` must exist before it starts. --}}
<script defer src="{{ asset('assets/libs/alpinejs/alpine.min.js') }}"></script>
