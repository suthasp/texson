<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerSiteRequest;
use App\Models\Customer;
use App\Models\CustomerSite;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * หน้างานของลูกค้า — Phase 1 ใช้เลือกในใบเสนอราคา, Phase 2 จะผูกกับ asset ที่ต้องทำ PM
 */
class CustomerSiteController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function create(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.sites.create', [
            'customer' => $customer,
            'site' => new CustomerSite(['is_active' => true]),
            'contacts' => $customer->contacts()->orderBy('name')->get(),
        ]);
    }

    public function store(CustomerSiteRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $site = $this->customers->addSite($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('เพิ่มหน้างาน :name แล้ว', ['name' => $site->site_name]));
    }

    public function edit(Customer $customer, CustomerSite $site): View
    {
        $this->authorize('update', $customer);
        abort_unless($site->customer_id === $customer->id, 404);

        return view('customers.sites.edit', [
            'customer' => $customer,
            'site' => $site,
            'contacts' => $customer->contacts()->orderBy('name')->get(),
        ]);
    }

    public function update(CustomerSiteRequest $request, Customer $customer, CustomerSite $site): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($site->customer_id === $customer->id, 404);

        $this->customers->updateSite($site, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('แก้ไขหน้างาน :name แล้ว', ['name' => $site->site_name]));
    }

    public function destroy(Customer $customer, CustomerSite $site): RedirectResponse
    {
        $this->authorize('update', $customer);
        abort_unless($site->customer_id === $customer->id, 404);

        $site->delete();

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('ลบหน้างาน :name แล้ว', ['name' => $site->site_name]));
    }
}
