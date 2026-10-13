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
