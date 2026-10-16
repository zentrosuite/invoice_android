@extends('layouts.app', [
    'pageTitle' => $customer->name,
    'breadcrumbs' => ['Customers' => route('customers.index')],
])
@section('title', $customer->name)

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex gap-3 align-items-center">
                        <span class="avatar-md flex-shrink-0">
                            <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-4 fw-semibold">
                                {{ Str::upper(Str::substr($customer->name, 0, 1)) }}
                            </span>
                        </span>
                        <div class="min-w-0">
                            <h5 class="mb-0 text-truncate">{{ $customer->name }}</h5>
                            @if ($customer->business_name)
                                <div class="text-muted">{{ $customer->business_name }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if ($customer->whatsappNumber())
                            <a href="https://wa.me/{{ $customer->whatsappNumber() }}" target="_blank" rel="noopener"
                               class="btn btn-success flex-fill"><i class="ri-whatsapp-line me-1"></i>WhatsApp</a>
                        @endif
                        @if ($customer->phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $customer->phone) }}" class="btn btn-soft-primary flex-fill">
                                <i class="ri-phone-line me-1"></i>Call
                            </a>
                        @endif
                        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-light flex-fill">
                            <i class="ri-pencil-line me-1"></i>Edit
                        </a>
                    </div>
                </div>

                <div class="card-body border-top">
                    <dl class="row mb-0 zi-dl">
                        <dt class="col-4">Phone</dt>
                        <dd class="col-8">{{ $customer->phone ?? '—' }}</dd>
                        <dt class="col-4">Email</dt>
                        <dd class="col-8 text-break">
                            @if ($customer->email)
                                <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                            @else — @endif
                        </dd>
                        <dt class="col-4">Address</dt>
                        <dd class="col-8">
                            @forelse ($customer->addressLines() as $line)
                                <div>{{ $line }}</div>
                            @empty — @endforelse
                        </dd>
                        @if ($customer->tax_id)
                            <dt class="col-4">GSTIN</dt>
                            <dd class="col-8">{{ $customer->tax_id }}</dd>
                        @endif
                    </dl>
                </div>

                @if ($customer->notes)
                    <div class="card-body border-top">
                        <div class="text-uppercase fs-11 fw-semibold text-muted mb-1">Private notes</div>
                        <div class="text-body" style="white-space: pre-line">{{ $customer->notes }}</div>
                    </div>
                @endif
            </div>

            {{-- ---- Advance ---------------------------------------------------------------------
                 Money received before an invoice. It waits here and is adjusted against this
                 customer's invoices from the invoice builder or an invoice page. --}}
            @php $adv = $errors->getBag('advance'); $sym = app(\App\Settings\InvoiceSettings::class)->currencySymbol; @endphp
            <div class="card" x-data="{ open: @js($adv->any()) }">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="me-auto">
                        <div class="text-uppercase fs-11 fw-semibold text-muted">Advance available</div>
                        <div class="fs-20 fw-semibold @if ((float) $advanceBalance > 0) text-success @endif">@money($advanceBalance, $sym)</div>
                    </div>
                    <button type="button" class="btn btn-soft-success" @click="open = true; $nextTick(() => $refs.advAmount.focus())">
                        <i class="ri-add-line me-1"></i>Record advance
                    </button>
                </div>

                @if ($advances->isNotEmpty() || $adjustments->isNotEmpty())
                    <div class="table-responsive border-top">
                        <table class="table align-middle mb-0 fs-13">
                            <tbody>
                            @foreach ($advances as $advance)
                                <tr>
                                    <td class="text-nowrap">{{ $advance->received_on->format('d/m/Y') }}</td>
                                    <td>
                                        Advance received · {{ $advance->method->label() }}
                                        @if ($advance->reference)<div class="text-muted fs-12">Ref {{ $advance->reference }}</div>@endif
                                        @if ($advance->note)<div class="text-muted fs-12">{{ $advance->note }}</div>@endif
                                    </td>
                                    <td class="text-end text-nowrap fw-medium text-success">+ @money($advance->amount, $sym)</td>
                                    <td class="text-end" style="width: 1%">
                                        <form method="POST" action="{{ route('customers.advances.destroy', [$customer, $advance]) }}"
                                              data-confirm="Remove this advance?" data-confirm-button="Remove">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-icon btn-sm btn-ghost-danger" aria-label="Remove advance"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($adjustments as $adjustment)
                                <tr @if ($adjustment->invoice) data-href="{{ route('invoices.show', $adjustment->invoice) }}" @endif>
                                    <td class="text-nowrap">{{ $adjustment->paid_on->format('d/m/Y') }}</td>
                                    <td>Adjusted against
                                        @if ($adjustment->invoice)
                                            <a href="{{ route('invoices.show', $adjustment->invoice) }}" class="fw-medium">{{ $adjustment->invoice->number }}</a>
                                        @else an invoice @endif
                                    </td>
                                    <td class="text-end text-nowrap fw-medium">− @money($adjustment->amount, $sym)</td>
                                    <td></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="zi-modal" x-show="open" x-cloak @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="adv-title">
                    <div class="zi-modal-backdrop" @click="open = false"></div>
                    <form method="POST" action="{{ route('customers.advances.store', $customer) }}" class="zi-modal-card card mb-0">
                        @csrf
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0" id="adv-title">Advance from {{ $customer->name }}</h5>
                            <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted fs-13">Money received before the invoice. It is kept for {{ $customer->name }} and adjusted against their invoices.</p>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="form-label" for="adv_amount">Amount received</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ $sym }}</span>
                                        <input type="text" id="adv_amount" name="amount" inputmode="decimal" x-ref="advAmount" required
                                               value="{{ old('amount') }}" class="form-control fw-semibold @if ($adv->has('amount')) is-invalid @endif">
                                    </div>
                                    @if ($adv->has('amount'))<div class="text-danger fs-13 mt-1">{{ $adv->first('amount') }}</div>@endif
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="adv_on">Date</label>
                                    <input type="date" id="adv_on" name="received_on" required value="{{ old('received_on', now()->toDateString()) }}"
                                           class="form-control @if ($adv->has('received_on')) is-invalid @endif">
                                    @if ($adv->has('received_on'))<div class="text-danger fs-13 mt-1">{{ $adv->first('received_on') }}</div>@endif
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="adv_method">Method</label>
                                    <select id="adv_method" name="method" class="form-select">
                                        @foreach (\App\Enums\PaymentMethod::received() as $method)
                                            <option value="{{ $method->value }}" @selected(old('method', 'upi') === $method->value)>{{ $method->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="adv_ref">Reference <span class="text-muted">(optional)</span></label>
                                    <input type="text" id="adv_ref" name="reference" maxlength="80" value="{{ old('reference') }}"
                                           placeholder="UPI ref / UTR / cheque no." class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="adv_note">Note <span class="text-muted">(optional)</span></label>
                                    <input type="text" id="adv_note" name="note" maxlength="255" value="{{ old('note') }}"
                                           placeholder="e.g. For the shop interior work" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" @click="open = false">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="ri-check-line me-1"></i>Save advance</button>
                        </div>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                  data-confirm="Remove {{ $customer->name }}?"
                  data-confirm-text="They disappear from your customer list. Invoices already made for them keep their details."
                  data-confirm-button="Remove">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link text-danger px-0">
                    <i class="ri-delete-bin-line me-1"></i>Remove customer
                </button>
            </form>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Invoices</h5>
                    @if (Route::has('invoices.create'))
                        <a href="{{ route('invoices.create', ['customer' => $customer->id]) }}" class="btn btn-primary btn-sm">
                            <i class="ri-add-line me-1"></i>New invoice
                        </a>
                    @endif
                </div>
                @if ($invoices->isEmpty())
                    <div class="card-body text-center text-muted py-5">
                        <i class="ri-file-list-3-line fs-1"></i>
                        <p class="mb-0 mt-2">No invoices for {{ $customer->name }} yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                            @foreach ($invoices as $invoice)
                                <tr data-href="{{ route('invoices.show', $invoice) }}">
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-body">{{ $invoice->number }}</a>
                                        <div class="text-muted fs-12">{{ $invoice->invoice_date->format('d M Y') }}</div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="fw-medium">@money($invoice->total, $invoice->currency_symbol)</div>
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
