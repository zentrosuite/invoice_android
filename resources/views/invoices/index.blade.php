@extends('layouts.app', ['pageTitle' => 'Invoices'])
@section('title', 'Invoices')

@php
    $tabs = ['' => 'All'] + collect(\App\Enums\InvoiceStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $all = $counts->sum();
@endphp

@section('content')
    @include('invoices.partials.unfinished')

    <div class="card">
        <div class="card-body border-bottom">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="{{ route('invoices.index') }}" class="flex-grow-1 zi-search" role="search">
                    @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
                    <div class="position-relative">
                        <input type="search" name="q" value="{{ $search }}" class="form-control ps-5"
                               placeholder="Search number, customer or phone" aria-label="Search invoices">
                        <i class="ri-search-line position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    </div>
                </form>
                {{-- The spreadsheet is written in the app, then offered to Android's "Save as". --}}
                <button type="button" class="btn btn-light" title="Save this list as a spreadsheet (CSV)"
                        x-data="{ busy: false }" :disabled="busy"
                        @click="busy = true; ZentroNative.post(@js(route('invoices.export')), @js(array_filter(['status' => $status?->value, 'q' => $search])))
                            .then((f) => ZentroNative.saveFile(f.path, f.name, f.mime))
                            .catch((e) => alert(e.message))
                            .finally(() => busy = false)">
                    <span x-show="busy" x-cloak class="spinner-border spinner-border-sm"></span>
                    <i class="ri-file-excel-2-line" x-show="!busy"></i><span class="d-none d-sm-inline ms-1">Export</span>
                </button>
                <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i>New invoice
                </a>
            </div>
        </div>

        {{-- Status filter. Pills inside the card's own padding — Velzon's card-header-tabs pull
             the strip out with negative margins, which on a phone pushed "All" past the card edge.
             The row scrolls sideways if the labels do not fit. --}}
        <nav class="zi-status-pills px-3 py-2" aria-label="Filter by status">
            @foreach ($tabs as $value => $label)
                @php
                    $count = $value === '' ? $all : ($counts[$value] ?? 0);
                    $active = ($status?->value ?? '') === $value;
                @endphp
                <a href="{{ route('invoices.index', array_filter(['status' => $value, 'q' => $search])) }}"
                   @class(['btn btn-sm rounded-pill', 'btn-primary' => $active, 'btn-light' => ! $active])
                   @if ($active) aria-current="page" @endif>
                    {{ $label }}
                    <span @class(['ms-1 fw-semibold', 'opacity-75' => $active, 'text-muted' => ! $active])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($invoices->isEmpty())
            <div class="card-body text-center py-5 border-top">
                @if ($all === 0)
                    <i class="ri-file-add-line fs-1 text-muted"></i>
                    <h5 class="mt-2">No invoices yet</h5>
                    <p class="text-muted mb-3">Create one, save it as a draft, and finish it whenever you like.</p>
                    <a href="{{ route('invoices.create') }}" class="btn btn-primary">Create your first invoice</a>
                @else
                    <i class="ri-search-eye-line fs-1 text-muted"></i>
                    <p class="mt-2 mb-0">Nothing here{{ $search !== '' ? ' for "' . $search . '"' : '' }}.</p>
                @endif
            </div>
        @else
            <div class="table-responsive border-top">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th class="d-none d-md-table-cell">Customer</th>
                        <th class="d-none d-lg-table-cell">Date</th>
                        <th class="text-end">Total</th>
                        <th class="d-none d-sm-table-cell text-end">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoices as $invoice)
                        <tr data-href="{{ route('invoices.show', $invoice) }}">
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-body">{{ $invoice->number }}</a>
                                <div class="text-muted fs-13 d-md-none text-truncate" style="max-width: 46vw">{{ $invoice->billedTo() }}</div>
                                <div class="text-muted fs-12 d-lg-none">{{ $invoice->invoice_date->format('d M Y') }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                {{ $invoice->billedTo() }}
                                @if ($invoice->customer_business && $invoice->customer_name)
                                    <div class="text-muted fs-13">{{ $invoice->customer_name }}</div>
                                @endif
                            </td>
                            <td class="d-none d-lg-table-cell text-nowrap">{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td class="text-end text-nowrap">
                                <span class="fw-medium">@money($invoice->total, $invoice->currency_symbol)</span>
                                @if ($invoice->status === \App\Enums\InvoiceStatus::Partial)
                                    <div class="text-muted fs-12">@money($invoice->balance(), $invoice->currency_symbol) due</div>
                                @endif
                                <div class="d-sm-none mt-1"><span class="badge {{ $invoice->status->badge() }}">{{ $invoice->status->label() }}</span></div>
                            </td>
                            <td class="d-none d-sm-table-cell text-end">
                                <span class="badge {{ $invoice->status->badge() }} fs-12">{{ $invoice->status->label() }}</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-muted fs-13">{{ $invoices->total() }} {{ Str::plural('invoice', $invoices->total()) }}</span>
                {{ $invoices->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
