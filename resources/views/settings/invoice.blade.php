@extends('layouts.app', ['pageTitle' => 'Invoice settings'])
@section('title', 'Invoice settings')

@php
    $current = $settings->profile();
@endphp

@section('content')
    {{-- Usage profile: its own form, because switching resets the columns below. --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <h5 class="card-title mb-0">Usage profile</h5>
            <span class="badge bg-primary-subtle text-primary fs-12">Currently: {{ $current->label() }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('settings.profile') }}"
                  data-confirm="Switch usage profile?"
                  data-confirm-text="Columns, labels and design will be reset to the new profile's defaults. Existing invoices are not changed."
                  data-confirm-button="Switch">
                @csrf
                @method('PUT')
                <div class="row g-2">
                    @foreach ($profiles as $profile)
                        <div class="col-sm-6 col-xxl-3">
                            <input type="radio" class="btn-check" name="usage_profile" id="up-{{ $profile->value }}"
                                   value="{{ $profile->value }}" @checked($profile === $current)>
                            <label class="zi-choice h-100" for="up-{{ $profile->value }}">
                                <i class="{{ $profile->icon() }} zi-choice-icon"></i>
                                <span class="d-block fw-medium">{{ $profile->label() }}</span>
                                <span class="d-block text-muted fs-12">{{ $profile->description() }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-soft-primary">Apply profile</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.update') }}"
          x-data="invoiceSettings(@js([
              'item_label' => old('item_label', $settings->itemLabel),
              'show_quantity' => (bool) old('show_quantity', $settings->showQuantity),
              'quantity_label' => old('quantity_label', $settings->quantityLabel),
              'rate_label' => old('rate_label', $settings->rateLabel),
              'number_prefix' => old('number_prefix', $settings->numberPrefix),
              'next_number' => (int) old('next_number', $settings->nextNumber),
              'number_padding' => (int) old('number_padding', $settings->numberPadding),
              'currency_symbol' => old('currency_symbol', $settings->currencySymbol),
              'default_tax_percent' => old('default_tax_percent', $settings->defaultTaxPercent),
          ]), @js(old('template', $settings->template)))">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Columns</h5></div>
                    <div class="card-body">
                        <div class="alert alert-info fs-13 py-2" x-show="template === 'package'" x-cloak>
                            <i class="ri-information-line me-1"></i>
                            The Package design lists services as <strong>Included / Not included</strong> with one
                            total, so these columns are used only when an invoice is switched to itemised pricing.
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="item_label">Description column</label>
                                <input type="text" id="item_label" name="item_label" required maxlength="40" x-model="f.item_label"
                                       class="form-control @error('item_label') is-invalid @enderror">
                                @error('item_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="rate_label">Price column</label>
                                <input type="text" id="rate_label" name="rate_label" required maxlength="20" x-model="f.rate_label"
                                       class="form-control @error('rate_label') is-invalid @enderror">
                                @error('rate_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch form-switch-md">
                                    <input type="hidden" name="show_quantity" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="show_quantity"
                                           name="show_quantity" value="1" x-model="f.show_quantity">
                                    <label class="form-check-label" for="show_quantity">Quantity column</label>
                                </div>
                                <div class="form-text">
                                    Off for flat-amount billing: each line is a description and an amount.
                                </div>
                            </div>
                            <div class="col-sm-6" x-show="f.show_quantity">
                                <label class="form-label" for="quantity_label">Quantity column name</label>
                                <input type="text" id="quantity_label" name="quantity_label" required maxlength="20" x-model="f.quantity_label"
                                       placeholder="Qty, Hours, Days…" class="form-control @error('quantity_label') is-invalid @enderror">
                                @error('quantity_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="text-uppercase fs-11 fw-semibold text-muted mt-4 mb-2">Preview</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 zi-col-preview">
                                <thead>
                                <tr>
                                    <th style="width: 28px">#</th>
                                    <th x-text="f.item_label"></th>
                                    <th class="text-end" x-show="f.show_quantity" x-text="f.quantity_label"></th>
                                    <th class="text-end" x-text="f.rate_label"></th>
                                    <th class="text-end" x-show="f.show_quantity">Amount</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Website design</td>
                                    <td class="text-end" x-show="f.show_quantity">2</td>
                                    <td class="text-end" x-text="money(f.show_quantity ? 1250 : 2500)"></td>
                                    <td class="text-end" x-show="f.show_quantity" x-text="money(2500)"></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Default wording</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_terms">Payment terms</label>
                                <input type="text" id="payment_terms" name="payment_terms" maxlength="80"
                                       value="{{ old('payment_terms', $settings->paymentTerms) }}" placeholder="e.g. On completion"
                                       class="form-control @error('payment_terms') is-invalid @enderror">
                                @error('payment_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="due_days">Due in (days)</label>
                                <input type="number" id="due_days" name="due_days" min="0" max="365" inputmode="numeric"
                                       value="{{ old('due_days', $settings->dueDays) }}" placeholder="Due on receipt"
                                       class="form-control @error('due_days') is-invalid @enderror">
                                <div class="form-text">Leave blank for "due on receipt".</div>
                                @error('due_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="default_terms">Terms &amp; conditions</label>
                                <textarea id="default_terms" name="default_terms" rows="5" maxlength="3000"
                                          placeholder="One condition per line — each becomes a bullet point."
                                          class="form-control @error('default_terms') is-invalid @enderror">{{ old('default_terms', $settings->defaultTerms) }}</textarea>
                                @error('default_terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="default_notes">Notes</label>
                                <textarea id="default_notes" name="default_notes" rows="2" maxlength="1000"
                                          placeholder="e.g. Goods once sold will not be taken back."
                                          class="form-control @error('default_notes') is-invalid @enderror">{{ old('default_notes', $settings->defaultNotes) }}</textarea>
                                @error('default_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="footer_note">Closing line</label>
                                <div class="form-text mt-n1 mb-1">Printed under “Thank You!” at the foot of the invoice.</div>
                                <input type="text" id="footer_note" name="footer_note" maxlength="120"
                                       value="{{ old('footer_note', $settings->footerNote) }}"
                                       class="form-control @error('footer_note') is-invalid @enderror">
                                @error('footer_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-3 mb-0">These pre-fill every new invoice; you can still edit them per invoice.</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Numbering</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="number_prefix">Prefix</label>
                                <input type="text" id="number_prefix" name="number_prefix" maxlength="12" x-model="f.number_prefix"
                                       class="form-control @error('number_prefix') is-invalid @enderror">
                                @error('number_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="number_padding">Digits</label>
                                <select id="number_padding" name="number_padding" x-model.number="f.number_padding"
                                        class="form-select @error('number_padding') is-invalid @enderror">
                                    @foreach (range(1, 8) as $digits)
                                        <option value="{{ $digits }}">{{ $digits }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="next_number">Next number</label>
                                <input type="number" id="next_number" name="next_number" min="1" required inputmode="numeric"
                                       x-model.number="f.next_number"
                                       class="form-control @error('next_number') is-invalid @enderror">
                                @error('next_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">Next invoice</label>
                                <div class="form-control bg-light-subtle fw-semibold text-primary" x-text="nextInvoice()"></div>
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-3 mb-0">
                            Coming from paper or another app? Set the next number to carry on your series.
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Money</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="currency_symbol">Currency symbol</label>
                                <input type="text" id="currency_symbol" name="currency_symbol" required maxlength="5"
                                       x-model="f.currency_symbol"
                                       class="form-control @error('currency_symbol') is-invalid @enderror">
                                @error('currency_symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="default_tax_percent">Default tax %</label>
                                <input type="number" id="default_tax_percent" name="default_tax_percent" min="0" max="100" step="0.01"
                                       inputmode="decimal" x-model="f.default_tax_percent" placeholder="No tax"
                                       class="form-control @error('default_tax_percent') is-invalid @enderror">
                                @error('default_tax_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <p class="text-muted fs-12 mt-3 mb-0">
                            A single tax line (e.g. 18%) added to the subtotal. Leave blank if you do not charge tax;
                            it can still be set per invoice.
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Design</h5></div>
                    <div class="card-body">
                        @php $selectedTemplate = old('template', $settings->template); @endphp
                        <div class="d-grid gap-2">
                            @foreach ($templates as $template)
                                <div>
                                    <input type="radio" class="btn-check" name="template" id="tpl-{{ $template->value }}"
                                           value="{{ $template->value }}" @checked($selectedTemplate === $template->value)
                                           x-model="template">
                                    <label class="zi-choice" for="tpl-{{ $template->value }}">
                                        <span class="d-block fw-medium">{{ $template->label() }}</span>
                                        <span class="d-block text-muted fs-12">{{ $template->description() }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('template')<div class="text-danger fs-13 mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="zi-form-actions">
            <button type="submit" class="btn btn-primary px-4"><i class="ri-save-3-line me-1"></i>Save settings</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('invoiceSettings', (initial, template) => ({
                f: initial,
                template: template,
                nextInvoice() {
                    const n = String(Math.max(1, parseInt(this.f.next_number, 10) || 1));
                    return (this.f.number_prefix || '') + n.padStart(parseInt(this.f.number_padding, 10) || 1, '0');
                },
                money(value) {
                    return (this.f.currency_symbol || '') + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                },
            }));
        });
    </script>
@endpush
