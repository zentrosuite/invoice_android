@extends('layouts.app', ['pageTitle' => 'Customers'])
@section('title', 'Customers')

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <form method="GET" action="{{ route('customers.index') }}" class="flex-grow-1 zi-search" role="search">
                    <div class="position-relative">
                        <input type="search" name="q" value="{{ $search }}" class="form-control ps-5"
                               placeholder="Search name, business, phone or email" aria-label="Search customers">
                        <i class="ri-search-line position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    </div>
                </form>
                <a href="{{ route('customers.create') }}" class="btn btn-primary">
                    <i class="ri-user-add-line me-1"></i>New customer
                </a>
            </div>
        </div>

        @if ($customers->isEmpty())
            <div class="card-body text-center py-5">
                @if ($search !== '')
                    <i class="ri-search-eye-line fs-1 text-muted"></i>
                    <p class="mt-2 mb-3">No customer matches "<strong>{{ $search }}</strong>".</p>
                    <a href="{{ route('customers.create', ['name' => $search]) }}" class="btn btn-soft-primary">
                        Add "{{ Str::limit($search, 30) }}" as a new customer
                    </a>
                @else
                    <i class="ri-group-line fs-1 text-muted"></i>
                    <h5 class="mt-2">No customers yet</h5>
                    <p class="text-muted mb-3">Save a customer once and pick them on every invoice after that.</p>
                    <a href="{{ route('customers.create') }}" class="btn btn-primary">Add your first customer</a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th class="d-none d-md-table-cell">Phone</th>
                        <th class="d-none d-lg-table-cell">Email</th>
                        <th class="d-none d-lg-table-cell">City</th>
                        <th class="text-end" style="width: 1%"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($customers as $customer)
                        <tr data-href="{{ route('customers.show', $customer) }}">
                            <td>
                                <a href="{{ route('customers.show', $customer) }}" class="fw-medium text-body">
                                    {{ $customer->name }}
                                </a>
                                @if ($customer->business_name)
                                    <div class="text-muted fs-13">{{ $customer->business_name }}</div>
                                @endif
                                {{-- Phone under the name on a phone, where its own column is hidden. --}}
                                @if ($customer->phone)
                                    <div class="text-muted fs-13 d-md-none">{{ $customer->phone }}</div>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell text-nowrap">{{ $customer->phone ?? '—' }}</td>
                            <td class="d-none d-lg-table-cell">{{ $customer->email ?? '—' }}</td>
                            <td class="d-none d-lg-table-cell">{{ $customer->city ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-icon btn-ghost-primary"
                                   title="Edit {{ $customer->name }}" aria-label="Edit {{ $customer->name }}">
                                    <i class="ri-pencil-line fs-16"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-muted fs-13">
                    @if ($search !== '')
                        {{ $customers->total() }} of {{ $total }} {{ Str::plural('customer', $total) }}
                    @else
                        {{ $total }} {{ Str::plural('customer', $total) }}
                    @endif
                </span>
                {{ $customers->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
