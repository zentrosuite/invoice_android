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

                    @yield('content')
                </div>
            </div>

            @include('layouts.partials.footer')
        </div>
    </div>

    <div class="vertical-overlay"></div>
@endsection
