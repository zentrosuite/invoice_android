@php
    $editing = $invoice->exists;
    $title = $editing ? "Edit {$invoice->number}" : 'New invoice';

    // The builder's starting state. After a failed save it is rebuilt from old() so nothing
    // typed is lost; otherwise from the invoice (a blank one for "new").
    $oldItems = old('items');
    $items = is_array($oldItems)
        ? array_values(array_map(fn ($i) => [
            'description' => $i['description'] ?? '',
            'details' => $i['details'] ?? '',
            'quantity' => $i['quantity'] ?? '',
            'rate' => $i['rate'] ?? '',
            'inclusion' => $i['inclusion'] ?? 'included',
        ], $oldItems))
        : $invoice->items->map(fn ($i) => [
            'description' => $i->description,
            'details' => $i->details ?? '',
            'quantity' => $i->quantity === null ? '' : $i->quantityForDisplay(),
            'rate' => $i->rate ?? ($invoice->isPackage() ? '' : $i->amount),
            'inclusion' => $i->inclusion?->value ?? 'included',
        ])->values()->all();

    // After a failed save, re-select whichever customer was picked, not just one from the URL.
    $selectedCustomerId = old('customer_id', $customer?->id);
    if ($selectedCustomerId && (! $customer || (string) $customer->id !== (string) $selectedCustomerId)) {
        $customer = \App\Models\Customer::withTrashed()->find($selectedCustomerId);
    }
    $selected = $customer && (string) $customer->id === (string) $selectedCustomerId ? [
        'id' => $customer->id,
        'name' => $customer->name,
        'business_name' => $customer->business_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
        'address' => implode(', ', $customer->addressLines()),
        'label' => $customer->label(),
    ] : null;

    $callout = old('callout', $invoice->callout ?? []);

    // With quantity shown, the price column is a unit rate. A profile that calls it "Amount"
    // (flat-fee billing) would otherwise head two columns "Amount".
    $rateHeading = strcasecmp($invoice->rate_label, 'Amount') === 0 ? 'Rate' : $invoice->rate_label;
    $packageTemplate = \App\Enums\InvoiceTemplate::Package->value;
    $itemisedDefault = app(\App\Settings\InvoiceSettings::class)->templateEnum()->isPackage()
        ? \App\Enums\InvoiceTemplate::Classic->value
        : app(\App\Settings\InvoiceSettings::class)->template;

    $state = [
        'mode' => old('pricing_mode', $invoice->pricing_mode->value),
        'template' => old('template', $invoice->template),
        'itemisedTemplate' => $invoice->templateEnum()->isPackage() ? $itemisedDefault : $invoice->template,
        'showQuantity' => (bool) old('show_quantity', $invoice->show_quantity),
        'items' => $items,
        'packageTotal' => old('package_total', $invoice->package_total ?? ''),
        'discountType' => old('discount_type', $invoice->discount_type ?? 'amount'),
        'discountValue' => old('discount_value', $invoice->discount_value ?? ''),
        'taxPercent' => old('tax_percent', $invoice->tax_percent ?? ''),
        'currency' => $invoice->currency_symbol,
        'customer' => $selected,
        'walkin' => [
            'name' => old('walkin_name', $invoice->customer_id === null ? $invoice->customer_name : ''),
            'phone' => old('walkin_phone', $invoice->customer_id === null ? $invoice->customer_phone : ''),
        ],
        'walkinMode' => $selected === null && ($editing || old('walkin_name') !== null),
        'callout' => [
            'enabled' => trim((string) ($callout['title'] ?? '')) !== '',
            'title' => $callout['title'] ?? '',
            'text' => $callout['text'] ?? '',
            'amount' => $callout['amount'] ?? '',
            'suffix' => $callout['suffix'] ?? '/ year',
            'meta' => $callout['meta'] ?? '',
        ],
        'urls' => [
            'search' => route('customers.search'),
            'quick' => route('customers.quick'),
        ],
    ];
@endphp
@extends('layouts.app', [
    'pageTitle' => $title,
    'breadcrumbs' => ['Invoices' => route('invoices.index')],
])
@section('title', $title)

@section('content')
    <form method="POST" action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}"
          data-no-refresh x-data="invoiceBuilder(@js($state))" @submit="onSubmit($event)" @input="dirty = true"
          class="zi-builder" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <input type="hidden" name="pricing_mode" :value="mode">
        <input type="hidden" name="template" :value="mode === 'package' ? '{{ $packageTemplate }}' : itemisedTemplate">
        <input type="hidden" name="show_quantity" :value="showQuantity ? 1 : 0">
        @unless ($editing)
            {{-- Which save button was pressed. A hidden field rather than the buttons' own name/value
                 (the buttons disable themselves on submit, and a disabled submitter can drop out
                 of the form data). Written directly by onSubmit(), never through an Alpine
                 binding: bindings update a tick later, after the browser has read the form. --}}
            <input type="hidden" name="status" value="draft" x-ref="saveAs">
        @endunless

        @error('items')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="row g-3">
            <div class="col-xl-8">
                {{-- ---- Bill to ------------------------------------------------------------ --}}
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">Bill to</h5>
                        <button type="button" class="btn btn-link btn-sm p-0" x-show="!customer"
                                @click="walkinMode = !walkinMode"
                                x-text="walkinMode ? 'Pick a saved customer' : 'Walk-in / not saved'"></button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="customer_id" :value="customer ? customer.id : ''">

                        {{-- Selected customer --}}
                        <template x-if="customer">
                            <div class="zi-billto">
                                <div class="min-w-0">
                                    <div class="fw-semibold fs-15" x-text="customer.name"></div>
                                    <div class="text-body" x-show="customer.business_name" x-text="customer.business_name"></div>
                                    <div class="text-muted fs-13 mt-1">
                                        <span x-show="customer.phone" x-text="customer.phone"></span>
                                        <span x-show="customer.phone && customer.email"> · </span>
                                        <span x-show="customer.email" x-text="customer.email"></span>
                                    </div>
                                    <div class="text-muted fs-13" x-show="customer.address" x-text="customer.address"></div>
                                </div>
                                <button type="button" class="btn btn-soft-dark btn-sm flex-shrink-0" @click="clearCustomer()">
                                    Change
                                </button>
                            </div>
                        </template>

                        {{-- Picker --}}
                        <div x-show="!customer && !walkinMode" class="position-relative" @click.outside="picker.open = false">
                            <div class="position-relative">
                                <input type="search" class="form-control form-control-lg ps-5 @error('customer_id') is-invalid @enderror"
                                       placeholder="Search customers by name or phone" autocomplete="off"
                                       x-model="picker.term" x-ref="customerSearch"
                                       @focus="openPicker()" @input.debounce.250ms="search()"
                                       @keydown.arrow-down.prevent="movePicker(1)" @keydown.arrow-up.prevent="movePicker(-1)"
                                       @keydown.enter.prevent="choosePicker()" @keydown.escape="picker.open = false">
                                <i class="ri-user-search-line position-absolute top-50 translate-middle-y ms-3 fs-18 text-muted"></i>
                            </div>
                            <div class="dropdown-menu show w-100 zi-picker shadow" x-show="picker.open" x-cloak>
                                <template x-if="picker.loading">
                                    <div class="dropdown-item-text text-muted fs-13">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Searching…
                                    </div>
                                </template>
                                <template x-for="(row, index) in picker.results" :key="row.id">
                                    <button type="button" class="dropdown-item py-2" :class="{ active: index === picker.active }"
                                            @click="selectCustomer(row)" @mouseenter="picker.active = index">
                                        <span class="d-block fw-medium text-truncate" x-text="row.label"></span>
                                        <span class="d-block fs-12 text-muted text-truncate" x-text="row.phone || row.email || ''"></span>
                                    </button>
                                </template>
                                <template x-if="!picker.loading && picker.results.length === 0 && picker.term.trim() !== ''">
                                    <div class="dropdown-item-text text-muted fs-13">No saved customer matches.</div>
                                </template>
                                <div class="dropdown-divider my-1"></div>
                                <button type="button" class="dropdown-item text-primary fw-medium" @click="openQuick()">
                                    <i class="ri-user-add-line me-1"></i>
                                    <span x-text="picker.term.trim() ? 'Add “' + picker.term.trim() + '” as a new customer' : 'Add a new customer'"></span>
                                </button>
                            </div>
                            @error('customer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Walk-in --}}
                        <div x-show="!customer && walkinMode" x-cloak class="row g-2">
                            <div class="col-sm-7">
                                <label class="form-label" for="walkin_name">Name <span class="text-muted">(optional)</span></label>
                                <input type="text" id="walkin_name" name="walkin_name" maxlength="150" x-model="walkin.name"
                                       :disabled="!!customer" placeholder="Cash customer"
                                       class="form-control @error('walkin_name') is-invalid @enderror">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label" for="walkin_phone">Phone <span class="text-muted">(for WhatsApp)</span></label>
                                <input type="tel" id="walkin_phone" name="walkin_phone" maxlength="30" inputmode="tel" x-model="walkin.phone"
                                       :disabled="!!customer"
                                       class="form-control @error('walkin_phone') is-invalid @enderror">
                                @error('walkin_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 form-text mt-1">Not saved to your customer list.</div>
                        </div>
                    </div>
                </div>

                {{-- ---- Lines -------------------------------------------------------------- --}}
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0">{{ $invoice->item_label }}s</h5>
                        <div class="btn-group zi-segmented" role="group" aria-label="Pricing">
                            <input type="radio" class="btn-check" id="mode-package" value="package" x-model="mode" @change="dirty = true">
                            <label class="btn btn-outline-primary btn-sm" for="mode-package">
                                <i class="ri-checkbox-multiple-line me-1"></i>Package
                            </label>
                            <input type="radio" class="btn-check" id="mode-itemised" value="itemised" x-model="mode" @change="dirty = true">
                            <label class="btn btn-outline-primary btn-sm" for="mode-itemised">
                                <i class="ri-list-ordered me-1"></i>Itemised
                            </label>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-13 mb-3" x-show="mode === 'package'">
                            List what the job covers and mark each as <strong>Included</strong> or <strong>Not included</strong>.
                            The customer pays one package price.
                        </p>
                        <div class="form-check form-switch mb-3" x-show="mode === 'itemised'">
                            <input class="form-check-input" type="checkbox" role="switch" id="show_quantity_toggle" x-model="showQuantity">
                            <label class="form-check-label" for="show_quantity_toggle">
                                {{ $invoice->quantity_label }} &times; {{ $rateHeading }}
                            </label>
                            <span class="form-text ms-1" x-show="!showQuantity">— off: one amount per line</span>
                        </div>

                        {{-- Column headings, desktop only; each line labels its own fields on a phone. --}}
                        <div class="zi-line-head d-none d-md-flex">
                            <span class="zi-col-num">#</span>
                            <span class="zi-col-desc">{{ $invoice->item_label }}</span>
                            <span class="zi-col-qty" x-show="mode === 'itemised' && showQuantity">{{ $invoice->quantity_label }}</span>
                            <span class="zi-col-rate" x-show="mode === 'itemised'" x-text="showQuantity ? @js($rateHeading) : 'Amount'"></span>
                            <span class="zi-col-amt" x-show="mode === 'itemised' && showQuantity">Amount</span>
                            <span class="zi-col-inc" x-show="mode === 'package'">Status</span>
                            <span class="zi-col-act"></span>
                        </div>

                        <template x-for="(item, index) in items" :key="item.key">
                            <div class="zi-line">
                                <span class="zi-col-num" x-text="String(index + 1).padStart(2, '0')"></span>

                                <div class="zi-col-desc">
                                    <input type="text" class="form-control" maxlength="255"
                                           :name="'items[' + index + '][description]'" x-model="item.description"
                                           :placeholder="mode === 'package' ? 'e.g. Professional Website' : @js($invoice->item_label)"
                                           :aria-label="'Line ' + (index + 1) + ' description'"
                                           @keydown.enter.prevent="addLine(index)">
                                    <template x-if="item.showDetails || item.details">
                                        <textarea class="form-control form-control-sm mt-1" maxlength="1000"
                                                  :rows="Math.max(2, (item.details || '').split('\n').length)"
                                                  :name="'items[' + index + '][details]'" x-model="item.details"
                                                  placeholder="Sub-points, one per line — e.g. Responsive design"></textarea>
                                    </template>
                                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 fs-12"
                                            x-show="!item.showDetails && !item.details" @click="item.showDetails = true">
                                        <i class="ri-add-line"></i> Sub-points
                                    </button>
                                </div>

                                <template x-if="mode === 'itemised' && showQuantity">
                                    <div class="zi-col-qty">
                                        <label class="zi-mobile-label">{{ $invoice->quantity_label }}</label>
                                        <input type="text" inputmode="decimal" class="form-control text-end"
                                               :name="'items[' + index + '][quantity]'" x-model="item.quantity" placeholder="1">
                                    </div>
                                </template>
                                <template x-if="mode === 'itemised'">
                                    <div class="zi-col-rate">
                                        <label class="zi-mobile-label" x-text="showQuantity ? @js($rateHeading) : 'Amount'"></label>
                                        <input type="text" inputmode="decimal" class="form-control text-end"
                                               :name="'items[' + index + '][rate]'" x-model="item.rate" placeholder="0.00">
                                    </div>
                                </template>
                                <template x-if="mode === 'itemised' && showQuantity">
                                    <div class="zi-col-amt">
                                        <label class="zi-mobile-label">Amount</label>
                                        <div class="zi-amount" x-text="money(lineAmount(item))"></div>
                                    </div>
                                </template>
                                <template x-if="mode === 'package'">
                                    <div class="zi-col-inc">
                                        <input type="hidden" :name="'items[' + index + '][inclusion]'" :value="item.inclusion">
                                        <button type="button" class="btn btn-sm w-100 zi-inclusion"
                                                :class="item.inclusion === 'included' ? 'btn-soft-success' : 'btn-soft-dark'"
                                                @click="item.inclusion = item.inclusion === 'included' ? 'excluded' : 'included'; dirty = true"
                                                :aria-pressed="item.inclusion === 'included'">
                                            <i :class="item.inclusion === 'included' ? 'ri-check-line' : 'ri-close-line'"></i>
                                            <span x-text="item.inclusion === 'included' ? 'Included' : 'Not included'"></span>
                                        </button>
                                    </div>
                                </template>

                                <div class="zi-col-act">
                                    <button type="button" class="btn btn-icon btn-sm btn-ghost-dark" title="Move up"
                                            :disabled="index === 0" @click="move(index, -1)"><i class="ri-arrow-up-s-line"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-ghost-dark" title="Move down"
                                            :disabled="index === items.length - 1" @click="move(index, 1)"><i class="ri-arrow-down-s-line"></i></button>
                                    <button type="button" class="btn btn-icon btn-sm btn-ghost-danger" title="Remove line"
                                            @click="removeLine(index)"><i class="ri-delete-bin-line"></i></button>
                                </div>
                            </div>
                        </template>

                        <button type="button" class="btn btn-soft-primary mt-2" @click="addLine()">
                            <i class="ri-add-line me-1"></i>Add line
                        </button>
                    </div>
                </div>

                {{-- ---- Extras ------------------------------------------------------------- --}}
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Extras</h5></div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="callout_enabled" x-model="callout.enabled">
                            <label class="form-check-label" for="callout_enabled">Highlighted note</label>
                            <div class="form-text">A box that stands out — e.g. an annual renewal charged separately.</div>
                        </div>
                        <div class="row g-2 mb-3 zi-callout-fields" x-show="callout.enabled" x-cloak>
                            <div class="col-sm-8">
                                <label class="form-label" for="callout_title">Title</label>
                                <input type="text" id="callout_title" maxlength="120" class="form-control"
                                       :name="callout.enabled ? 'callout[title]' : ''" x-model="callout.title"
                                       placeholder="Annual Domain Renewal · example.in">
                            </div>
                            <div class="col-6 col-sm-2">
                                <label class="form-label" for="callout_amount">Amount</label>
                                <input type="text" id="callout_amount" inputmode="decimal" class="form-control"
                                       :name="callout.enabled ? 'callout[amount]' : ''" x-model="callout.amount" placeholder="899">
                            </div>
                            <div class="col-6 col-sm-2">
                                <label class="form-label" for="callout_suffix">Per</label>
                                <input type="text" id="callout_suffix" maxlength="20" class="form-control"
                                       :name="callout.enabled ? 'callout[suffix]' : ''" x-model="callout.suffix" placeholder="/ year">
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label" for="callout_text">Text</label>
                                <input type="text" id="callout_text" maxlength="255" class="form-control"
                                       :name="callout.enabled ? 'callout[text]' : ''" x-model="callout.text"
                                       placeholder="This renewal fee is separate from the project price.">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="callout_meta">Small print</label>
                                <input type="text" id="callout_meta" maxlength="80" class="form-control"
                                       :name="callout.enabled ? 'callout[meta]' : ''" x-model="callout.meta"
                                       placeholder="Renews on 25/09/2027">
                            </div>
                            @error('callout.title')<div class="col-12 text-danger fs-13">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="terms">Terms &amp; conditions</label>
                            <textarea id="terms" name="terms" rows="5" maxlength="3000" placeholder="One per line"
                                      class="form-control @error('terms') is-invalid @enderror">{{ old('terms', $invoice->terms) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="1000"
                                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div>
                            <label class="form-label" for="footer_note">Closing line</label>
                            <input type="text" id="footer_note" name="footer_note" maxlength="120"
                                   value="{{ old('footer_note', $invoice->footer_note) }}"
                                   class="form-control @error('footer_note') is-invalid @enderror">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="zi-sticky">
                    {{-- ---- Invoice details ------------------------------------------------ --}}
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Invoice</h5></div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label" for="number">Number</label>
                                    <input type="text" id="number" name="number" maxlength="30"
                                           value="{{ old('number', $invoice->number) }}"
                                           class="form-control fw-semibold @error('number') is-invalid @enderror">
                                    @error('number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="invoice_date">Date</label>
                                    <input type="date" id="invoice_date" name="invoice_date" required
                                           value="{{ old('invoice_date', $invoice->invoice_date?->toDateString()) }}"
                                           class="form-control @error('invoice_date') is-invalid @enderror">
                                    @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="due_date">Due</label>
                                    <input type="date" id="due_date" name="due_date"
                                           value="{{ old('due_date', $invoice->due_date?->toDateString()) }}"
                                           class="form-control @error('due_date') is-invalid @enderror">
                                    @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="payment_terms">Payment terms</label>
                                    <input type="text" id="payment_terms" name="payment_terms" maxlength="80"
                                           value="{{ old('payment_terms', $invoice->payment_terms) }}" placeholder="e.g. On completion"
                                           class="form-control @error('payment_terms') is-invalid @enderror">
                                </div>
                                <div class="col-12" x-show="mode === 'itemised'">
                                    <label class="form-label" for="itemised_template">Design</label>
                                    <select id="itemised_template" class="form-select" x-model="itemisedTemplate">
                                        @foreach ($templates as $template)
                                            @continue($template->isPackage())
                                            <option value="{{ $template->value }}">{{ $template->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ---- Totals --------------------------------------------------------- --}}
                    <div class="card">
                        <div class="card-header"><h5 class="card-title mb-0">Total</h5></div>
                        <div class="card-body">
                            <div x-show="mode === 'package'" class="mb-3">
                                <label class="form-label" for="package_total">Package price</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text" x-text="currency"></span>
                                    <input type="text" id="package_total" inputmode="decimal" placeholder="0.00"
                                           :name="mode === 'package' ? 'package_total' : ''" x-model="packageTotal"
                                           class="form-control fw-semibold text-end @error('package_total') is-invalid @enderror">
                                </div>
                                @error('package_total')<div class="text-danger fs-13 mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="d-flex justify-content-between mb-2" x-show="mode === 'itemised'">
                                <span class="text-muted">Subtotal</span>
                                <span class="fw-medium" x-text="money(subtotal())"></span>
                            </div>

                            <label class="form-label mb-1" for="discount_value">Discount</label>
                            <div class="input-group mb-1">
                                <input type="text" id="discount_value" name="discount_value" inputmode="decimal" placeholder="0"
                                       x-model="discountValue"
                                       class="form-control text-end @error('discount_value') is-invalid @enderror">
                                <select name="discount_type" class="form-select flex-grow-0 w-auto" x-model="discountType" aria-label="Discount type">
                                    <option value="amount" x-text="currency"></option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                            @error('discount_value')<div class="text-danger fs-13">{{ $message }}</div>@enderror
                            <div class="text-end text-muted fs-13 mb-2" x-show="discount() > 0" x-text="'− ' + money(discount())"></div>

                            <label class="form-label mb-1" for="tax_percent">Tax %</label>
                            <div class="input-group mb-1">
                                <input type="text" id="tax_percent" name="tax_percent" inputmode="decimal" placeholder="No tax"
                                       x-model="taxPercent"
                                       class="form-control text-end @error('tax_percent') is-invalid @enderror">
                                <span class="input-group-text">%</span>
                            </div>
                            @error('tax_percent')<div class="text-danger fs-13">{{ $message }}</div>@enderror
                            <div class="text-end text-muted fs-13" x-show="tax() > 0" x-text="'+ ' + money(tax())"></div>

                            <div class="zi-total mt-3">
                                <span>Total</span>
                                <span x-text="money(total())"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Desktop: under the totals, in the sticky column. --}}
                    <div class="d-none d-md-block">
                        @include('invoices.partials.builder-actions')
                    </div>
                </div>
            </div>
        </div>

        {{-- Phone: at form level, so it stays stuck to the bottom of the screen while the lines are
             being typed. Inside the right-hand column it would only stick while that column is in view. --}}
        <div class="d-md-none zi-sticky-bottom">
            @include('invoices.partials.builder-actions')
        </div>

        {{-- ---- Quick-add customer -------------------------------------------------------- --}}
        <div class="zi-modal" x-show="quick.open" x-cloak @keydown.escape.window="quick.open = false" role="dialog" aria-modal="true" aria-labelledby="quick-title">
            <div class="zi-modal-backdrop" @click="quick.open = false"></div>
            <div class="zi-modal-card card mb-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0" id="quick-title">New customer</h5>
                    <button type="button" class="btn-close" @click="quick.open = false" aria-label="Close"></button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="q_name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="q_name" class="form-control" x-model="quick.form.name" x-ref="quickName"
                                   :class="{ 'is-invalid': quick.errors.name }">
                            <div class="invalid-feedback" x-text="quick.errors.name"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="q_business">Business name</label>
                            <input type="text" id="q_business" class="form-control" x-model="quick.form.business_name">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="q_phone">Phone / WhatsApp</label>
                            <input type="tel" id="q_phone" inputmode="tel" class="form-control" x-model="quick.form.phone"
                                   :class="{ 'is-invalid': quick.errors.phone }">
                            <div class="invalid-feedback" x-text="quick.errors.phone"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="q_email">Email</label>
                            <input type="email" id="q_email" class="form-control" x-model="quick.form.email"
                                   :class="{ 'is-invalid': quick.errors.email }">
                            <div class="invalid-feedback" x-text="quick.errors.email"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="q_address">Address</label>
                            <input type="text" id="q_address" class="form-control" x-model="quick.form.address_line1">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="q_city">City</label>
                            <input type="text" id="q_city" class="form-control" x-model="quick.form.city">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="q_state">State</label>
                            <input type="text" id="q_state" class="form-control" x-model="quick.form.state">
                        </div>
                    </div>
                    <div class="text-danger fs-13 mt-2" x-show="quick.failed" x-text="quick.failed"></div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light" @click="quick.open = false">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="saveQuick()" :disabled="quick.saving">
                        <span x-show="quick.saving" class="spinner-border spinner-border-sm me-1"></span>Save &amp; use
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('assets/app/invoice-builder.js') }}?v={{ config('app.asset_version') }}"></script>
@endpush
