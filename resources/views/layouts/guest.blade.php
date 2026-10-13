@extends('layouts.base')

{{-- Sign-in shell from Velzon's auth pages. Same base as the app shell — see layouts.base. --}}
@section('body')
    <div class="auth-page-wrapper pt-5">
        <div class="auth-one-bg-position auth-one-bg zi-auth-bg">
            <div class="bg-overlay"></div>
            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1440 120">
                    <path d="M 0,36 C 144,53.6 432,88.8 720,89 C 1008,89.2 1296,54.4 1440,37L1440 140L0 140z"></path>
                </svg>
            </div>
        </div>

        <div class="auth-page-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center mt-sm-5 mb-4">
                            <a href="{{ route('login') }}" class="d-inline-block auth-logo">
                                <img src="{{ asset('assets/images/zentro-logo-white.png') }}" alt="Zentro" height="52">
                            </a>
                            <p class="mt-2 fs-15 fw-medium text-white-50 mb-0">Invoices</p>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card mt-4">
                            <div class="card-body p-4">
                                @yield('card')
                            </div>
                        </div>
                        @yield('below-card')
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="container">
                <p class="mb-0 text-muted text-center">&copy; {{ now()->year }} {{ config('app.name') }}</p>
            </div>
        </footer>
    </div>
@endsection
