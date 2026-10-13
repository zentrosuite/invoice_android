@extends('layouts.app', [
    'pageTitle' => $invoice->number,
    'breadcrumbs' => ['Invoices' => route('invoices.index')],
])
@section('title', $invoice->number)

@use('App\Enums\InvoiceStatus')
@php
    $s = $invoice->status;
    $sym = $invoice->currency_symbol;
@endphp

@section('content')
    {{-- ---- Status and actions ---------------------------------------------------------- --}}
    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <span class="badge {{ $s->badge() }} fs-13 me-2">{{ $s->label() }}</span>
                <span class="text-muted fs-13">
                    @if ($s === InvoiceStatus::Paid && $invoice->paid_at)
                        Paid on {{ $invoice->paid_at->format('d M Y') }}
                    @elseif ($invoice->due_date && $s === InvoiceStatus::Sent)
                        Due {{ $invoice->due_date->format('d M Y') }}
                        @if ($invoice->due_date->isPast()) <span class="text-danger">· overdue</span> @endif
                    @else
                        Dated {{ $invoice->invoice_date->format('d M Y') }}
                    @endif
                </span>
            </div>

            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary"><i class="ri-pencil-line me-1"></i>Edit</a>

            @if ($s === InvoiceStatus::Draft)
                <form method="POST" action="{{ route('invoices.status', $invoice) }}">
                    @csrf @method('PUT')
                    <button name="status" value="sent" class="btn btn-soft-primary"><i class="ri-send-plane-line me-1"></i>Finalise</button>
                </form>
            @endif
            @if ($s->isPayable())
                <form method="POST" action="{{ route('invoices.status', $invoice) }}">
                    @csrf @method('PUT')
                    <button name="status" value="paid" class="btn btn-success"><i class="ri-check-double-line me-1"></i>Mark paid</button>
                </form>
            @endif

            <div class="dropdown">
                <button class="btn btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                    <i class="ri-more-2-fill"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">
                        @csrf
                        <button class="dropdown-item"><i class="ri-file-copy-line me-2 text-muted"></i>Duplicate</button>
                    </form>
                    @if ($s === InvoiceStatus::Paid)
                        <form method="POST" action="{{ route('invoices.status', $invoice) }}">
                            @csrf @method('PUT')
                            <button name="status" value="sent" class="dropdown-item"><i class="ri-arrow-go-back-line me-2 text-muted"></i>Mark unpaid</button>
                        </form>
                    @endif
                    @if ($s === InvoiceStatus::Cancelled)
                        <form method="POST" action="{{ route('invoices.status', $invoice) }}">
                            @csrf @method('PUT')
                            <button name="status" value="draft" class="dropdown-item"><i class="ri-restart-line me-2 text-muted"></i>Reopen as draft</button>
                        </form>
                    @elseif ($s !== InvoiceStatus::Draft)
                        <form method="POST" action="{{ route('invoices.status', $invoice) }}"
                              data-confirm="Cancel {{ $invoice->number }}?" data-confirm-text="It stays on record, marked cancelled. You can reopen it later." data-confirm-button="Cancel invoice">
                            @csrf @method('PUT')
                            <button name="status" value="cancelled" class="dropdown-item text-danger"><i class="ri-close-circle-line me-2"></i>Cancel invoice</button>
                        </form>
                    @endif
                    @if ($s === InvoiceStatus::Draft)
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
                              data-confirm="Delete draft {{ $invoice->number }}?" data-confirm-text="This cannot be undone." data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button class="dropdown-item text-danger"><i class="ri-delete-bin-line me-2"></i>Delete draft</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ---- The invoice ---------------------------------------------------------------------- --}}
    <div class="card">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <div class="text-uppercase fs-11 fw-semibold text-primary mb-1">Bill to</div>
                    @if ($invoice->customer_name || $invoice->customer_business)
                        <div class="fw-semibold fs-15">{{ $invoice->customer_name }}</div>
                        @if ($invoice->customer_business)<div>{{ $invoice->customer_business }}</div>@endif
                        @if ($invoice->customer_phone)<div class="text-muted fs-13">{{ $invoice->customer_phone }}</div>@endif
                        @if ($invoice->customer_email)<div class="text-muted fs-13">{{ $invoice->customer_email }}</div>@endif
                        @foreach (\App\Models\Invoice::lines($invoice->customer_address) as $line)
                            <div class="text-muted fs-13">{{ $line }}</div>
                        @endforeach
                        @if ($invoice->customer_id)
                            <a href="{{ route('customers.show', $invoice->customer_id) }}" class="fs-13">View customer</a>
                        @endif
                    @else
                        <div class="text-muted">Walk-in customer</div>
                    @endif
                </div>
                <div class="col-sm-6 text-sm-end">
                    <dl class="mb-0 zi-meta">
                        <dt>Invoice date</dt><dd>{{ $invoice->invoice_date->format('d/m/Y') }}</dd>
                        @if ($invoice->due_date)<dt>Due date</dt><dd>{{ $invoice->due_date->format('d/m/Y') }}</dd>@endif
                        @if ($invoice->payment_terms)<dt>Payment terms</dt><dd>{{ $invoice->payment_terms }}</dd>@endif
                        <dt>Design</dt><dd>{{ $invoice->templateEnum()->label() }}</dd>
                    </dl>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 40px">#</th>
                        <th>{{ $invoice->item_label }}</th>
                        @if ($invoice->isPackage())
                            <th class="text-end">Status</th>
                        @else
                            @if ($invoice->show_quantity)
                                <th class="text-end d-none d-sm-table-cell">{{ $invoice->quantity_label }}</th>
                                <th class="text-end d-none d-sm-table-cell">{{ strcasecmp($invoice->rate_label, 'Amount') === 0 ? 'Rate' : $invoice->rate_label }}</th>
                            @endif
                            <th class="text-end">Amount</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="text-muted">{{ str_pad((string) $item->position, 2, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div class="fw-medium">{{ $item->description }}</div>
                                @if ($item->detailLines())
                                    <ul class="zi-subpoints">
                                        @foreach ($item->detailLines() as $point)<li>{{ $point }}</li>@endforeach
                                    </ul>
                                @endif
                                @if (! $invoice->isPackage() && $invoice->show_quantity)
                                    <div class="text-muted fs-12 d-sm-none">{{ $item->quantityForDisplay() }} × @money($item->rate, $sym)</div>
                                @endif
                            </td>
                            @if ($invoice->isPackage())
                                <td class="text-end">
                                    @if ($item->inclusion === \App\Enums\Inclusion::Excluded)
                                        <span class="badge bg-dark-subtle text-body"><i class="ri-close-line"></i> Not included</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success"><i class="ri-check-line"></i> Included</span>
                                    @endif
                                </td>
                            @else
                                @if ($invoice->show_quantity)
                                    <td class="text-end d-none d-sm-table-cell">{{ $item->quantityForDisplay() }}</td>
                                    <td class="text-end d-none d-sm-table-cell text-nowrap">@money($item->rate, $sym)</td>
                                @endif
                                <td class="text-end text-nowrap fw-medium">@money($item->amount, $sym)</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6 order-2 order-md-1">
                    <div class="text-uppercase fs-11 fw-semibold text-muted">Amount in words</div>
                    <div class="fst-italic">{{ $words }}</div>
                </div>
                <div class="col-md-6 order-1 order-md-2">
                    <dl class="zi-totals mb-0">
                        <dt>{{ $invoice->isPackage() ? 'Package total' : 'Subtotal' }}</dt><dd>@money($invoice->subtotal, $sym)</dd>
                        @if ((float) $invoice->discount_amount > 0)
                            <dt>Discount{{ $invoice->discount_type === 'percent' ? ' (' . rtrim(rtrim($invoice->discount_value, '0'), '.') . '%)' : '' }}</dt>
                            <dd>− @money($invoice->discount_amount, $sym)</dd>
                        @endif
                        @if ((float) $invoice->tax_amount > 0)
                            <dt>Tax ({{ rtrim(rtrim($invoice->tax_percent, '0'), '.') }}%)</dt><dd>@money($invoice->tax_amount, $sym)</dd>
                        @endif
                    </dl>
                    <div class="zi-total mt-2"><span>Total</span><span>@money($invoice->total, $sym)</span></div>
                </div>
            </div>

            @if ($invoice->hasCallout())
                @php $c = $invoice->callout; @endphp
                <div class="zi-callout mt-4">
                    <div class="min-w-0">
                        <div class="fw-semibold">{{ $c['title'] }}</div>
                        @if (! empty($c['text']))<div class="text-muted fs-13">{{ $c['text'] }}</div>@endif
                    </div>
                    @if (! empty($c['amount']))
                        <div class="text-end flex-shrink-0">
                            <div class="fw-bold text-primary fs-16">{{ \App\Support\Money::short($c['amount'], $sym) }} <span class="fs-13 fw-normal text-muted">{{ $c['suffix'] ?? '' }}</span></div>
                            @if (! empty($c['meta']))<div class="text-muted fs-12">{{ $c['meta'] }}</div>@endif
                        </div>
                    @endif
                </div>
            @endif

            @if ($invoice->termsList())
                <div class="mt-4">
                    <div class="text-uppercase fs-11 fw-semibold text-primary mb-1">Terms &amp; conditions</div>
                    <ul class="mb-0 ps-3 fs-13">
                        @foreach ($invoice->termsList() as $term)<li>{{ $term }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($invoice->notes)
                <div class="mt-3 fs-13" style="white-space: pre-line">{{ $invoice->notes }}</div>
            @endif
            @if ($invoice->footer_note)
                <div class="mt-3 fw-semibold">{{ $invoice->footer_note }}</div>
            @endif
        </div>
    </div>
@endsection
