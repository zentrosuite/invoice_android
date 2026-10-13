@inject('navigation', 'App\Support\Navigation')

<div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
        <a href="{{ route('dashboard') }}" class="logo logo-dark">
            <span class="logo-sm"><img src="{{ asset('assets/images/zentro-mark.png') }}" alt="" height="26"></span>
            <span class="logo-lg zi-logo-lg">
                <img src="{{ asset('assets/images/zentro-logo-dark-text.png') }}" alt="Zentro" height="28">
                <span class="zi-brand-sub">Invoices</span>
            </span>
        </a>
        <a href="{{ route('dashboard') }}" class="logo logo-light">
            <span class="logo-sm"><img src="{{ asset('assets/images/zentro-mark.png') }}" alt="" height="26"></span>
            <span class="logo-lg zi-logo-lg">
                <img src="{{ asset('assets/images/zentro-logo-white.png') }}" alt="Zentro" height="28">
                <span class="zi-brand-sub">Invoices</span>
            </span>
        </a>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <div id="two-column-menu"></div>
            <ul class="navbar-nav" id="navbar-nav">
                @foreach ($navigation->items() as $item)
                    @if ($item['heading'] ?? false)
                        <li class="menu-title"><span>{{ $item['label'] }}</span></li>
                    @else
                        <li class="nav-item">
                            <a href="{{ $item['url'] }}"
                               @class(['nav-link', 'menu-link', 'active' => $item['active']])
                               @if ($item['active']) aria-current="page" @endif>
                                <i class="{{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
