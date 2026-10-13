@php
    $editing = $customer->exists;
    $title = $editing ? 'Edit customer' : 'New customer';
    // A new customer can arrive pre-named from a search that found nobody (?name=…).
    $v = fn (string $field) => old($field, $editing ? $customer->{$field} : ($field === 'name' ? request('name') : null));
@endphp
@extends('layouts.app', [
    'pageTitle' => $title,
    'breadcrumbs' => ['Customers' => route('customers.index')],
])
@section('title', $title)

@section('content')
    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Who</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ $v('name') }}" required maxlength="150"
                                       autocomplete="off" @unless ($editing) autofocus @endunless
                                       class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="business_name">Business name</label>
                                <input type="text" id="business_name" name="business_name" value="{{ $v('business_name') }}" maxlength="150"
                                       autocomplete="off" class="form-control @error('business_name') is-invalid @enderror">
                                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="phone">Phone / WhatsApp</label>
                                <input type="tel" id="phone" name="phone" value="{{ $v('phone') }}" maxlength="30" inputmode="tel"
                                       autocomplete="off" placeholder="98765 43210"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Invoices are shared to this number on WhatsApp.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" id="email" name="email" value="{{ $v('email') }}" maxlength="150"
                                       autocomplete="off" class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">Address</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Address line 1</label>
                                <input type="text" id="address_line1" name="address_line1" value="{{ $v('address_line1') }}" maxlength="150"
                                       class="form-control @error('address_line1') is-invalid @enderror">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Address line 2</label>
                                <input type="text" id="address_line2" name="address_line2" value="{{ $v('address_line2') }}" maxlength="150"
                                       class="form-control @error('address_line2') is-invalid @enderror">
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label" for="city">City</label>
                                <input type="text" id="city" name="city" value="{{ $v('city') }}" maxlength="80"
                                       class="form-control @error('city') is-invalid @enderror">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="state">State</label>
                                <input type="text" id="state" name="state" value="{{ $v('state') }}" maxlength="80"
                                       class="form-control @error('state') is-invalid @enderror">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="pincode">PIN code</label>
                                <input type="text" id="pincode" name="pincode" value="{{ $v('pincode') }}" maxlength="12" inputmode="numeric"
                                       class="form-control @error('pincode') is-invalid @enderror">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h5 class="card-title mb-0">More</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="tax_id">GSTIN / tax ID</label>
                            <input type="text" id="tax_id" name="tax_id" value="{{ $v('tax_id') }}" maxlength="40"
                                   class="form-control text-uppercase @error('tax_id') is-invalid @enderror">
                            <div class="form-text">Printed under the customer's address when filled in.</div>
                        </div>
                        <div>
                            <label class="form-label" for="notes">Private notes</label>
                            <textarea id="notes" name="notes" rows="4" maxlength="2000"
                                      class="form-control @error('notes') is-invalid @enderror">{{ $v('notes') }}</textarea>
                            <div class="form-text">Only you see these. Never printed on an invoice.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="zi-form-actions">
            <a href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="ri-save-3-line me-1"></i>{{ $editing ? 'Save changes' : 'Save customer' }}
            </button>
        </div>
    </form>
@endsection
