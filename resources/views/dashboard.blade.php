@extends('layouts.app')
@section('title', 'Dashboard')

@php
    $setupSteps = [
        ['done' => (bool) $company->addressLine1, 'title' => 'Company profile', 'text' => 'Logo, address and contact details for the invoice header.', 'route' => 'company.edit'],
        ['done' => (bool) $company->upiId, 'title' => 'UPI ID', 'text' => 'So every unpaid invoice carries a QR your customer can scan to pay.', 'route' => 'company.edit'],
        ['done' => $hasCustomers, 'title' => 'First customer', 'text' => 'Save them once, pick them on every invoice after.', 'route' => 'customers.create'],
        ['done' => $hasInvoices, 'title' => 'First invoice', 'text' => 'Save as draft, then send it on WhatsApp.', 'route' => 'invoices.create'],
    ];
    $setupLeft = collect($setupSteps)->where('done', false)->count();
    $delta = (float) $receivedLast > 0 ? round(((float) $received - (float) $receivedLast) / (float) $receivedLast * 100) : null;
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 mt-1">
        <div class="flex-grow-1">
            <h4 class="fs-16 mb-1">{{ $company->name }}</h4>
            <p class="text-muted mb-0">{{ now()->format('l, d M Y') }}</p>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-light"><i class="ri-user-add-line me-1"></i>Customer</a>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="ri-add-line me-1"></i>New invoice</a>
    </div>

    {{-- Your data lives only on this phone: a nudge when no copy has been kept for a week. --}}
    @if ($backupOverdue)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2" role="alert">
            <i class="ri-shield-check-line fs-18"></i>
            <span class="me-auto">
                @if ($lastBackup)
                    Last backup {{ $lastBackup->diffForHumans() }}. Keep a fresh copy off this phone.
                @else
                    Your invoices are only on this phone. Save a backup to Google Drive or WhatsApp.
                @endif
            </span>
            <a href="{{ route('backup.index') }}" class="btn btn-warning btn-sm">Back up now</a>
        </div>
    @endif

    {{-- ---- Figures ------------------------------------------------------------------------ --}}
    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <a href="{{ route('invoices.index', ['status' => 'sent']) }}" class="card zi-stat h-100 mb-0">
                <div class="card-body">
                    <div class="zi-stat-label">Outstanding</div>
                    <div class="zi-stat-value">{{ \App\Support\Money::short($outstanding->amount, $symbol) }}</div>
                    <div class="zi-stat-sub">{{ $outstanding->n }} {{ Str::plural('invoice', $outstanding->n) }} unpaid</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a href="{{ route('invoices.index', ['status' => 'sent']) }}" @class(['card zi-stat h-100 mb-0', 'zi-stat-alert' => $overdue->n > 0])>
                <div class="card-body">
                    <div class="zi-stat-label">Overdue</div>
                    <div class="zi-stat-value">{{ \App\Support\Money::short($overdue->amount, $symbol) }}</div>
                    <div class="zi-stat-sub">{{ $overdue->n > 0 ? $overdue->n . ' past due date' : 'Nothing overdue' }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card zi-stat h-100 mb-0">
                <div class="card-body">
                    <div class="zi-stat-label">Received in {{ now()->format('F') }}</div>
                    <div class="zi-stat-value">{{ \App\Support\Money::short($received, $symbol) }}</div>
                    <div class="zi-stat-sub">
                        @if ($delta !== null)
                            <span @class(['text-success' => $delta >= 0, 'text-danger' => $delta < 0])>{{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}%</span>
                            vs {{ now()->subMonthNoOverflow()->format('F') }}
                        @else
                            Payments recorded this month
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <a href="{{ route('invoices.index', ['status' => 'draft']) }}" class="card zi-stat h-100 mb-0">
                <div class="card-body">
                    <div class="zi-stat-label">Drafts</div>
                    <div class="zi-stat-value">{{ $drafts }}</div>
                    <div class="zi-stat-sub">{{ $drafts ? 'Waiting to be sent' : 'All sent' }}</div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mt-0">
        {{-- ---- Unpaid ------------------------------------------------------------------- --}}
        <div class="col-xl-7">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">To collect</h5>
                    <a href="{{ route('invoices.index', ['status' => 'sent']) }}" class="fs-13">All unpaid</a>
                </div>
                @if ($unpaid->isEmpty())
                    <div class="card-body text-center text-muted py-5">
                        <i class="ri-emotion-happy-line fs-1"></i>
                        <p class="mb-0 mt-2">Nothing outstanding. Every invoice is paid.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                            @foreach ($unpaid as $invoice)
                                @php $late = $invoice->due_date?->isBefore(today()); @endphp
                                <tr data-href="{{ route('invoices.show', $invoice) }}">
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-body">{{ $invoice->billedTo() }}</a>
                                        <div class="text-muted fs-12">
                                            {{ $invoice->number }} ·
                                            @if ($late)
                                                <span class="text-danger">overdue {{ $invoice->due_date->diffForHumans(['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}</span>
                                            @elseif ($invoice->due_date)
                                                due {{ $invoice->due_date->format('d M') }}
                                            @else
                                                {{ $invoice->invoice_date->format('d M') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="fw-semibold">@money($invoice->balance(), $invoice->currency_symbol)</div>
                                        @if ($invoice->status === \App\Enums\InvoiceStatus::Partial)
                                            <span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-5">
            @if ($setupLeft > 0)
                {{-- ---- Getting started — gone once everything is done ---------------------- --}}
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">Getting started</h5>
                        <span class="badge bg-primary-subtle text-primary">{{ 4 - $setupLeft }} / 4</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 zi-checklist">
                            @foreach ($setupSteps as $step)
                                <li @class(['d-flex gap-3 py-2', 'is-done' => $step['done']])>
                                    <span class="zi-check-icon flex-shrink-0"><i class="{{ $step['done'] ? 'ri-check-line' : 'ri-arrow-right-line' }}"></i></span>
                                    <span>
                                        @if ($step['done'])
                                            <span class="d-block fw-medium">{{ $step['title'] }}</span>
                                        @else
                                            <a href="{{ route($step['route']) }}" class="d-block fw-medium">{{ $step['title'] }}</a>
                                        @endif
                                        <span class="d-block text-muted fs-13">{{ $step['text'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ---- Recent --------------------------------------------------------------------- --}}
            <div class="card mb-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Recent</h5>
                    <a href="{{ route('invoices.index') }}" class="fs-13">All invoices</a>
                </div>
                @if ($recent->isEmpty())
                    <div class="card-body text-muted text-center py-4">No invoices yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                            @foreach ($recent as $invoice)
                                <tr data-href="{{ route('invoices.show', $invoice) }}">
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-medium text-body">{{ $invoice->number }}</a>
                                        <div class="text-muted fs-12 text-truncate" style="max-width: 46vw">{{ $invoice->billedTo() }}</div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="fs-13">@money($invoice->total, $invoice->currency_symbol)</div>
                                        <span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
