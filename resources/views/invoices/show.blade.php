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
    {{-- x-data so the Record payment button can dispatch to the payment form below. --}}
    <div class="card" x-data>
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <span class="badge {{ $s->badge() }} fs-13 me-2">{{ $s->label() }}</span>
                <span class="text-muted fs-13">
                    @if ($s === InvoiceStatus::Paid && $invoice->paid_at)
                        Paid on {{ $invoice->paid_at->format('d M Y') }}
                    @elseif ($s === InvoiceStatus::Partial)
                        @money($invoice->amount_paid, $sym) paid ·
                        <span class="fw-semibold text-body">@money($invoice->balance(), $sym) due</span>
                        @if ($invoice->due_date?->isPast()) <span class="text-danger">· overdue</span> @endif
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
            @if ($s->isPayable() && (float) $invoice->balance() > 0)
                <button type="button" class="btn btn-success" @click="$dispatch('record-payment')">
                    <i class="ri-money-rupee-circle-line me-1"></i>Record payment
                </button>
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
                    @if ($s->isPayable() && (float) $invoice->balance() > 0)
                        <form method="POST" action="{{ route('invoices.status', $invoice) }}">
                            @csrf @method('PUT')
                            <button name="status" value="paid" class="dropdown-item"><i class="ri-check-double-line me-2 text-muted"></i>Mark fully paid</button>
                        </form>
                    @endif
                    @if ($invoice->hasPayments())
                        <form method="POST" action="{{ route('invoices.status', $invoice) }}"
                              data-confirm="Mark {{ $invoice->number }} unpaid?" data-confirm-text="Every payment recorded against it will be removed." data-confirm-button="Mark unpaid">
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

    {{-- ---- Payments received ------------------------------------------------------------------ --}}
    @if ($invoice->payments->isNotEmpty())
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">Payments</h6>
                <span class="fs-13">
                    @money($invoice->amount_paid, $sym) of @money($invoice->total, $sym)
                    @if ((float) $invoice->balance() > 0) · <span class="fw-semibold">@money($invoice->balance(), $sym) due</span>@endif
                </span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td class="text-nowrap">{{ $payment->paid_on->format('d M Y') }}</td>
                            <td>
                                {{ $payment->method->label() }}
                                @if ($payment->reference)<div class="text-muted fs-12">Ref {{ $payment->reference }}</div>@endif
                                @if ($payment->note)<div class="text-muted fs-12">{{ $payment->note }}</div>@endif
                            </td>
                            <td class="text-end text-nowrap fw-medium">@money($payment->amount, $sym)</td>
                            <td class="text-end" style="width: 1%">
                                <form method="POST" action="{{ route('invoices.payments.destroy', [$invoice, $payment]) }}"
                                      data-confirm="Remove this payment?" data-confirm-button="Remove">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-icon btn-sm btn-ghost-danger" aria-label="Remove payment"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ---- Record a payment ------------------------------------------------------------------- --}}
    @php $pay = $errors->getBag('payment'); @endphp
    <div x-data="{ open: @js($pay->any()) }" @record-payment.window="open = true; $nextTick(() => $refs.amount.select())">
        <div class="zi-modal" x-show="open" x-cloak @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-labelledby="pay-title">
            <div class="zi-modal-backdrop" @click="open = false"></div>
            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="zi-modal-card card mb-0">
                @csrf
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0" id="pay-title">Record payment</h5>
                    <button type="button" class="btn-close" @click="open = false" aria-label="Close"></button>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label" for="pay_amount">Amount received</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ $sym }}</span>
                                <input type="text" id="pay_amount" name="amount" inputmode="decimal" x-ref="amount" required
                                       value="{{ old('amount', $invoice->balance()) }}"
                                       class="form-control fw-semibold @if ($pay->has('amount')) is-invalid @endif">
                            </div>
                            @if ($pay->has('amount'))<div class="text-danger fs-13 mt-1">{{ $pay->first('amount') }}</div>@endif
                            <div class="form-text">Balance @money($invoice->balance(), $sym). Enter less for a part payment.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="pay_on">Date</label>
                            <input type="date" id="pay_on" name="paid_on" required value="{{ old('paid_on', now()->toDateString()) }}"
                                   class="form-control @if ($pay->has('paid_on')) is-invalid @endif">
                            @if ($pay->has('paid_on'))<div class="text-danger fs-13 mt-1">{{ $pay->first('paid_on') }}</div>@endif
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="pay_method">Method</label>
                            <select id="pay_method" name="method" class="form-select">
                                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                    <option value="{{ $method->value }}" @selected(old('method', 'upi') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="pay_ref">Reference <span class="text-muted">(optional)</span></label>
                            <input type="text" id="pay_ref" name="reference" maxlength="80" value="{{ old('reference') }}"
                                   placeholder="UPI ref / UTR / cheque no." class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pay_note">Note <span class="text-muted">(optional)</span></label>
                            <input type="text" id="pay_note" name="note" maxlength="255" value="{{ old('note') }}" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light" @click="open = false">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="ri-check-line me-1"></i>Save payment</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ---- Send to the customer --------------------------------------------------------------
         On the phone the PDF itself goes: WhatsApp opens on this customer's chat with it attached
         (ZentroWhatsApp plugin), or the share sheet, or Android's "Save as". Each first asks the
         server to render the PDF into the app's storage (invoices.pdf-file). --}}
    <div class="card zi-share" x-data="sendBox(@js([
        'pdfUrl' => route('invoices.pdf-file', $invoice),
        'sharedUrl' => route('invoices.shared', $invoice),
        'phone' => $invoice->whatsappNumber(),
        'text' => $shareText,
        'app' => app(\App\Settings\InvoiceSettings::class)->whatsappApp,
    ]))">
        <div class="card-body">
            <div class="mb-2">
                <h6 class="mb-0">Send to {{ $invoice->customer_name ?: ($invoice->customer_business ?: 'customer') }}</h6>
                <div class="text-muted fs-12">
                    @if ($invoice->shared_at)
                        Sent {{ $invoice->shared_at->diffForHumans() }}
                    @elseif ($invoice->status === InvoiceStatus::Draft)
                        Sending finalises this draft as unpaid.
                    @else
                        Not sent yet
                    @endif
                </div>
            </div>

            <div class="d-grid gap-2 zi-send-grid">
                <button type="button" class="btn btn-success btn-lg" @click="whatsapp()" :disabled="busy">
                    <span x-show="busy === 'whatsapp'" class="spinner-border spinner-border-sm me-1"></span>
                    <i class="ri-whatsapp-line me-1" x-show="busy !== 'whatsapp'"></i>Send on WhatsApp
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-soft-success flex-fill" @click="share()" :disabled="busy">
                        <span x-show="busy === 'share'" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="ri-share-forward-line me-1" x-show="busy !== 'share'"></i>Share PDF
                    </button>
                    <button type="button" class="btn btn-soft-primary flex-fill" @click="save()" :disabled="busy">
                        <span x-show="busy === 'save'" class="spinner-border spinner-border-sm me-1"></span>
                        <i class="ri-download-2-line me-1" x-show="busy !== 'save'"></i>Save PDF
                    </button>
                </div>
            </div>

            @unless ($invoice->whatsappNumber())
                <div class="alert alert-warning py-2 px-3 fs-13 mt-3 mb-0">
                    <i class="ri-information-line me-1"></i>
                    No WhatsApp number on this invoice — WhatsApp will ask you to choose the contact.
                    @if ($invoice->customer_id)
                        <a href="{{ route('customers.edit', $invoice->customer_id) }}">Add a phone number</a>, then save the invoice again.
                    @endif
                </div>
            @endunless

            <div class="text-success fs-12 mt-2" x-show="note" x-text="note" x-cloak></div>
            <div class="text-danger fs-13 mt-2" x-show="error" x-text="error" x-cloak></div>
        </div>
    </div>

    <div class="zi-doc-toolbar">
        <a href="{{ route('invoices.preview', $invoice) }}" class="btn btn-light">
            <i class="ri-fullscreen-line me-1"></i>Full size
        </a>
        <span class="text-muted fs-13 ms-auto">{{ $invoice->templateEnum()->label() }} design</span>
    </div>

    <div class="zi-doc-frame" x-data="docFrame()" x-init="init()">
        <div class="zi-doc-scale" :style="`height: ${height * scale}px`">
            <iframe src="{{ route('invoices.preview', $invoice) }}" title="Invoice {{ $invoice->number }}"
                    x-ref="frame" @load="measure()" scrolling="no"
                    :style="`width: ${width}px; height: ${height}px; transform: translateX(-${width * scale / 2}px) scale(${scale})`"></iframe>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Fit the framed page to the available width, and size the frame to its content so the
        // page scrolls rather than the frame.
        document.addEventListener('alpine:init', () => {
            Alpine.data('sendBox', (cfg) => ({
                busy: null,
                error: '',
                note: '',

                /** Render the PDF into the app's storage; {path, name, mime, ms}. */
                pdf() {
                    return ZentroNative.post(cfg.pdfUrl);
                },
                run(kind, action) {
                    this.busy = kind;
                    this.error = '';
                    this.note = '';
                    return this.pdf()
                        .then((file) => {
                            this.note = 'PDF ready in ' + (file.ms / 1000).toFixed(1) + ' s';
                            return action(file);
                        })
                        .catch((e) => { this.error = e.message || 'Something went wrong.'; })
                        .finally(() => { this.busy = null; });
                },
                reported() {
                    ZentroNative.post(cfg.sharedUrl).catch(() => {});
                    // Back from WhatsApp: show the new status (a draft is now unpaid).
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') { location.reload(); }
                    }, { once: true });
                },
                whatsapp() {
                    this.run('whatsapp', (file) => ZentroNative.whatsappFile(file.path, cfg.phone, cfg.text, cfg.app)
                        .then(() => this.reported()));
                },
                share() {
                    this.run('share', (file) => ZentroNative.shareFile(file.path, cfg.text, file.name)
                        .then(() => this.reported()));
                },
                save() {
                    this.run('save', (file) => ZentroNative.saveFile(file.path, file.name, file.mime)
                        .then((saved) => { if (saved) { this.note = 'Saved as ' + saved; } }));
                },
            }));

            Alpine.data('docFrame', () => ({
                width: 820, height: 1123, scale: 1,
                init() {
                    window.addEventListener('resize', () => this.fit(), { passive: true });
                },
                measure() {
                    const doc = this.$refs.frame.contentDocument;
                    if (!doc) { return; }
                    const sheet = doc.querySelector('.sheet');
                    this.width = Math.ceil((sheet ? sheet.getBoundingClientRect().width : 794) + 48);
                    this.height = Math.ceil(doc.body.getBoundingClientRect().height);
                    this.fit();
                    // Fonts can land after load and change the height.
                    doc.fonts && doc.fonts.ready.then(() => { this.height = Math.ceil(doc.body.getBoundingClientRect().height); });
                },
                fit() {
                    // $root, not $el: called from the frame's @load, $el would be the iframe itself.
                    const available = this.$root.clientWidth;
                    this.scale = Math.min(1, available / this.width);
                },
            }));
        });
    </script>
@endpush
