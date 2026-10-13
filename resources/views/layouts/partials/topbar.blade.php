{{-- Ids and classes (topnav-hamburger-icon, light-dark-mode, data-toggle="fullscreen") are kept
     verbatim from Velzon because assets/js/theme-app.js binds to exactly these. --}}
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex align-items-center">
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('dashboard') }}" class="logo logo-dark">
                        <span class="logo-sm"><img src="{{ asset('assets/images/zentro-mark.png') }}" alt="" height="24"></span>
                        <span class="logo-lg"><img src="{{ asset('assets/images/zentro-logo-dark-text.png') }}" alt="" height="26"></span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="logo logo-light">
                        <span class="logo-sm"><img src="{{ asset('assets/images/zentro-mark.png') }}" alt="" height="24"></span>
                        <span class="logo-lg"><img src="{{ asset('assets/images/zentro-logo-white.png') }}" alt="" height="26"></span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger"
                        id="topnav-hamburger-icon" aria-label="Menu">
                    <span class="hamburger-icon"><span></span><span></span><span></span></span>
                </button>
            </div>

            <div class="d-flex align-items-center">
                @unless (request()->routeIs('invoices.create', 'invoices.edit'))
                    <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm me-1 d-flex align-items-center">
                        <i class="ri-add-line"></i><span class="d-none d-sm-inline ms-1">New invoice</span>
                    </a>
                @endunless

                {{-- Shown at every width: hiding it below 576px makes dark mode unreachable on a phone. --}}
                <div class="ms-1 header-item d-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle light-dark-mode"
                            title="Toggle dark mode">
                        <i class="bx bx-moon fs-22"></i>
                    </button>
                </div>

                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <span class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-semibold">
                                {{ (new \App\Models\User(['name' => app(\App\Settings\CompanySettings::class)->name]))->initials() }}
                            </span>
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ Str::limit(app(\App\Settings\CompanySettings::class)->name, 24) }}</span>
                            </span>
                        </span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">{{ app(\App\Settings\CompanySettings::class)->name }}</h6>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="mdi mdi-shield-lock-outline text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Security</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="mdi mdi-lock-outline text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">Lock</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
