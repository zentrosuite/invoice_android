@extends('layouts.base')

{{-- Authenticated shell: Velzon's vertical layout. .vertical-overlay closes the drawer on phones. --}}
@section('body')
    <div id="layout-wrapper">
        @include('layouts.partials.topbar')
        @include('layouts.partials.sidebar')

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    @includeWhen(isset($pageTitle), 'components.page-title', [
                        'pageTitle' => $pageTitle ?? '',
                        'breadcrumbs' => $breadcrumbs ?? [],
                    ])

                    @include('components.flash')

                    {{-- A newly uploaded version with database changes still to apply. --}}
                    @if (app(\App\Support\PendingMigrations::class)->any())
                        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="alert">
                            <i class="ri-download-cloud-2-line fs-18"></i>
                            <span class="me-auto">A new version is installed. Apply its database update to finish.</span>
                            <form method="POST" action="{{ route('system.update') }}">
                                @csrf
                                <button class="btn btn-info btn-sm">Apply update</button>
                            </form>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>

            @include('layouts.partials.footer')
        </div>
    </div>

    <div class="vertical-overlay"></div>
@endsection
