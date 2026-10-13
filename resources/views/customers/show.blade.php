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
                <div class="card-body text-center text-muted py-5">
                    <i class="ri-file-list-3-line fs-1"></i>
                    <p class="mb-0 mt-2">No invoices for {{ $customer->name }} yet.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
