@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-lg-center flex-lg-row flex-column gap-2 mb-3 mt-1">
                <div class="flex-grow-1">
                    <h4 class="fs-16 mb-1">Hello, {{ Str::before(auth()->user()->name, ' ') }}</h4>
                    <p class="text-muted mb-0">{{ $company->name }} &middot; {{ $profile->label() }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Getting started</h5></div>
                <div class="card-body">
                    @php
                        $steps = [
                            ['done' => true, 'icon' => 'ri-checkbox-circle-fill', 'title' => 'Set up your account', 'text' => 'Done. You are signed in.'],
                            ['done' => (bool) $company->addressLine1, 'icon' => 'ri-building-line', 'title' => 'Complete your company profile', 'text' => 'Logo, address and contact details for the invoice header.', 'route' => 'company.edit'],
                            ['done' => (bool) $company->upiId, 'icon' => 'ri-qr-code-line', 'title' => 'Add your UPI ID', 'text' => 'Every unpaid invoice will carry a QR code your customer can scan to pay.', 'route' => 'company.edit'],
                            ['done' => false, 'icon' => 'ri-settings-3-line', 'title' => 'Review invoice settings', 'text' => 'Columns, numbering, tax and your default terms.', 'route' => 'settings.edit'],
                            ['done' => false, 'icon' => 'ri-file-add-line', 'title' => 'Create your first invoice', 'text' => 'Add a customer, type your lines, save as draft or share on WhatsApp.'],
                        ];
                    @endphp
                    <ul class="list-unstyled mb-0 zi-checklist">
                        @foreach ($steps as $s)
                            <li @class(['d-flex gap-3 py-2', 'is-done' => $s['done']])>
                                <span class="zi-check-icon flex-shrink-0">
                                    <i class="{{ $s['done'] ? 'ri-check-line' : $s['icon'] }}"></i>
                                </span>
                                <span>
                                    @if (! $s['done'] && isset($s['route']) && Route::has($s['route']))
                                        <a href="{{ route($s['route']) }}" class="d-block fw-medium">{{ $s['title'] }}</a>
                                    @else
                                        <span class="d-block fw-medium">{{ $s['title'] }}</span>
                                    @endif
                                    <span class="d-block text-muted fs-13">{{ $s['text'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card zi-hero-card">
                <div class="card-body">
                    <div class="text-uppercase fs-12 fw-semibold zi-hero-eyebrow">Your invoices start as</div>
                    <h5 class="mt-2 mb-1 text-white">{{ $profile->label() }}</h5>
                    <p class="zi-hero-text fs-13 mb-0">{{ $profile->description() }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
