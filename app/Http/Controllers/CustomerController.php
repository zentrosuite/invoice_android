<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('customers.index', [
            'customers' => Customer::query()
                ->search($search)
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
            'total' => Customer::query()->count(),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->attributesForModel());

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} saved.");
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'invoices' => $customer->invoices()->orderByDesc('invoice_date')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->attributesForModel());

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    /**
     * Soft delete. Past invoices keep their copy of the customer's details either way.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', "{$customer->name} removed.");
    }

    /**
     * Add a customer from inside the invoice builder without leaving it.
     */
    public function quickStore(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->attributesForModel());

        return response()->json($this->pickerRow($customer), 201);
    }

    /**
     * Picker search for the invoice builder.
     */
    public function search(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->search((string) $request->query('q', ''))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json($customers->map(fn (Customer $c): array => $this->pickerRow($c)));
    }

    /**
     * @return array<string, mixed>
     */
    private function pickerRow(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'business_name' => $c->business_name,
            'phone' => $c->phone,
            'email' => $c->email,
            'address' => implode(', ', $c->addressLines()),
            'label' => $c->label(),
        ];
    }
}
