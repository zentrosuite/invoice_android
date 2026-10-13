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

                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-success" @click="reported()">
                    <i class="ri-whatsapp-line me-1"></i>Send on WhatsApp
                </a>
                <button type="button" class="btn btn-soft-success" x-show="canShareFiles" x-cloak @click="sharePdf()" :disabled="busy">
                    <span x-show="busy" class="spinner-border spinner-border-sm me-1"></span>
                    <i class="ri-share-forward-line me-1" x-show="!busy"></i>Share PDF
                </button>
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
                busy: false,
                copied: false,
                error: '',
                // The system share sheet with a FILE (so WhatsApp gets the PDF itself) exists on
                // phones; desktops get the WhatsApp link and copy-link only.
                canShareFiles: !!(navigator.canShare && window.File
                    && navigator.canShare({ files: [new File([''], 'x.pdf', { type: 'application/pdf' })] })),
                pdf: null,

                init() {
                    // Fetched ahead of the tap: the share sheet must open within the tap's
                    // "user activation", which a slow download could outlast.
                    if (this.canShareFiles) { this.loadPdf(); }
                },
                loadPdf() {
                    return this.pdf ??= fetch(cfg.pdfUrl, { credentials: 'same-origin' })
                        .then((r) => { if (!r.ok) { throw new Error(); } return r.blob(); })
                        .then((blob) => new File([blob], cfg.filename, { type: 'application/pdf' }));
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
                async sharePdf() {
                    this.error = '';
                    this.busy = true;
                    try {
                        const file = await this.loadPdf();
                        await navigator.share({ files: [file], text: cfg.text });
                        this.reported();
                    } catch (e) {
                        // AbortError is the person closing the sheet — not a failure.
                        if (e && e.name !== 'AbortError') { this.error = 'Could not open sharing. Use Send on WhatsApp instead.'; }
                        this.pdf = null;
                    } finally {
                        this.busy = false;
                    }
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
                    this.height = Math.ceil(doc.documentElement.scrollHeight);
                    this.fit();
                    // Fonts can land after load and change the height.
                    doc.fonts && doc.fonts.ready.then(() => { this.height = Math.ceil(doc.documentElement.scrollHeight); });
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
