<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerContactRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ผู้ติดต่อเป็นทรัพยากรลูกของลูกค้า — สิทธิ์อิงกับ CustomerPolicy ทั้งหมด
 */
class CustomerContactController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function create(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.contacts.create', [
            'customer' => $customer,
            'contact' => new CustomerContact(['is_primary' => $customer->contacts()->doesntExist()]),
        ]);
    }

    public function store(CustomerContactRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $contact = $this->customers->addContact($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('เพิ่มผู้ติดต่อ :name แล้ว', ['name' => $contact->name]));
    }

    public function edit(Customer $customer, CustomerContact $contact): View
    {
        $this->authorize('update', $customer);
        abort_unless($contact->customer_id === $customer->id, 404);

        return view('customers.contacts.edit', [
            'customer' => $customer,
            'contact' => $contact,
        ]);
    }

    public function update(CustomerContactRequest $request, Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($contact->customer_id === $customer->id, 404);

        $this->customers->updateContact($contact, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('แก้ไขผู้ติดต่อ :name แล้ว', ['name' => $contact->name]));
    }

    public function destroy(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($contact->customer_id === $customer->id, 404);

        $contact->delete();

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('ลบผู้ติดต่อ :name แล้ว', ['name' => $contact->name]));
    }
}
