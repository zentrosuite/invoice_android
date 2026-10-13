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

    {{-- ---- Send to the customer ------------------------------------------------------------
         WhatsApp is a plain link built on the server (see InvoiceController::show): opening it
         after an async request would be blocked as a popup. The press is reported in the
         background, which turns a draft into an unpaid invoice. --}}
    <div class="card zi-share" x-data="shareBox(@js([
        'sharedUrl' => route('invoices.shared', $invoice),
        'pdfUrl' => route('invoices.pdf', $invoice),
        'link' => $shareUrl,
        'text' => $shareText,
        'filename' => app(\App\Services\Invoices\InvoiceDocument::class)->filename($invoice),
    ]))">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <h6 class="mb-0">Send to {{ $invoice->customer_name ?: ($invoice->customer_business ?: 'customer') }}</h6>
                    <div class="text-success fs-12" x-show="phone" x-cloak>
                        <i class="ri-file-pdf-2-line"></i> Sends the PDF — choose WhatsApp, then
                        <strong>{{ $invoice->customer_name ?: ($invoice->customer_business ?: 'the customer') }}</strong>@if ($invoice->customer_phone) ({{ $invoice->customer_phone }})@endif.
                    </div>
                    <div class="text-muted fs-12">
                        @if ($invoice->viewed_at)
                            <i class="ri-eye-line"></i> Opened by the customer {{ $invoice->viewed_at->diffForHumans() }}
                        @elseif ($invoice->shared_at)
                            Sent {{ $invoice->shared_at->diffForHumans() }} · not opened yet
                        @elseif ($invoice->status === InvoiceStatus::Draft)
                            Sending finalises this draft as unpaid.
                        @else
                            Not sent yet
                        @endif
                    </div>
                </div>

                {{-- Phone: the share sheet with ONLY the PDF — WhatsApp, then the contact. No web
                     page can address a file to a WhatsApp number directly, so that is as direct as
                     it gets. Desktop: the customer's chat with the saved message and link. --}}
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-success"
                   :class="{ disabled: phone && !file }" :aria-disabled="phone && !file" @click="send($event)">
                    <span x-show="phone && !file && !failed" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                    <i class="ri-whatsapp-line me-1" x-show="!(phone && !file && !failed)"></i>
                    <span x-text="phone && !file && !failed ? 'Preparing PDF…' : 'Send on WhatsApp'">Send on WhatsApp</span>
                </a>
                <button type="button" class="btn btn-light" @click="copy()">
                    <i :class="copied ? 'ri-check-line' : 'ri-link'" class="me-1"></i><span x-text="copied ? 'Copied' : 'Copy link'"></span>
                </button>
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

            <div class="zi-share-link mt-3">
                <span class="text-muted fs-12 text-truncate">{{ $shareUrl }}</span>
                <form method="POST" action="{{ route('invoices.reset-link', $invoice) }}" class="flex-shrink-0"
                      data-confirm="Make a new link?" data-confirm-text="The link you already sent will stop working." data-confirm-button="New link">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0 fs-12">Reset link</button>
                </form>
            </div>
            <div class="text-danger fs-13 mt-2" x-show="error" x-text="error" x-cloak></div>
        </div>
    </div>

    {{-- ---- The invoice, in its design ---------------------------------------------------
         The very page the PDF is made from, framed and scaled down to fit. A frame rather than
         inline markup so the design's own styles never meet the app's. --}}
    <div class="zi-doc-toolbar">
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-primary">
            <i class="ri-download-2-line me-1"></i>Download PDF
        </a>
        <a href="{{ route('invoices.pdf', [$invoice, 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn btn-light">
            <i class="ri-printer-line me-1"></i>Print
        </a>
        <a href="{{ route('invoices.preview', $invoice) }}" target="_blank" rel="noopener" class="btn btn-light">
            <i class="ri-fullscreen-line me-1"></i><span class="d-none d-sm-inline">Full size</span><span class="d-sm-none">Open</span>
        </a>
        <span class="text-muted fs-13 ms-auto d-none d-md-inline">{{ $invoice->templateEnum()->label() }} design</span>
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
            Alpine.data('shareBox', (cfg) => ({
                copied: false,
                error: '',
                // A phone that can hand a FILE to the share sheet. Touch-primary as well as able to
                // share files: Chrome on a Windows laptop can share files too, but there the saved
                // message to the customer's chat is what is wanted.
                phone: window.matchMedia('(hover: none) and (pointer: coarse)').matches
                    && !!(navigator.canShare && window.File
                        && navigator.canShare({ files: [new File([''], 'x.pdf', { type: 'application/pdf' })] })),
                file: null,
                failed: false,

                init() {
                    // Downloaded before the tap: the share sheet only opens straight from a tap,
                    // and waiting on a download first would lose it.
                    if (this.phone) { this.loadPdf(); }
                },
                loadPdf() {
                    this.failed = false;
                    fetch(cfg.pdfUrl, { credentials: 'same-origin' })
                        .then((r) => { if (!r.ok) { throw new Error(); } return r.blob(); })
                        .then((blob) => { this.file = new File([blob], cfg.filename, { type: 'application/pdf' }); })
                        .catch(() => { this.failed = true; this.error = 'Could not prepare the PDF. Tap again to send the message and link instead.'; });
                },
                send(event) {
                    // Desktop, or the PDF could not be prepared: the wa.me link opens as usual.
                    if (!this.phone || this.failed) { this.reported(); return; }

                    event.preventDefault();
                    if (!this.file) { return; } // still preparing — the button says so

                    this.error = '';
                    navigator.share({ files: [this.file] })
                        .then(() => this.reported())
                        .catch((e) => {
                            // AbortError is closing the sheet without sending — not a failure.
                            if (e && e.name !== 'AbortError') {
                                this.failed = true;
                                this.error = 'Sharing did not open. Tap again to send the message and link instead.';
                            }
                        });
                },
                reported() {
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    fetch(cfg.sharedUrl, {
                        method: 'POST', keepalive: true, credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    }).catch(() => {});
                    // Back from WhatsApp: show the new status.
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') { location.reload(); }
                    }, { once: true });
                },
                async copy() {
                    try {
                        await navigator.clipboard.writeText(cfg.link);
                    } catch (e) {
                        const t = document.createElement('textarea');
                        t.value = cfg.link; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1800);
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
