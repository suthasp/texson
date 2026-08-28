<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PriceTier;
use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PersonalDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use SortsListings;

    /** @var array<int, string> */
    private const SORTABLE = ['code', 'name_th', 'province', 'credit_term_days', 'created_at'];

    public function __construct(
        private readonly CustomerService $customers,
        private readonly PersonalDataService $personalData,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('price_tier'), fn ($q) => $q->where('price_tier', $request->string('price_tier')->toString()))
            ->when($request->filled('province'), fn ($q) => $q->where('province', $request->string('province')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'))
            // คนที่มีสิทธิ์ลบถาวรต้องหาลูกค้าที่ถูกลบไปแล้วเจอ ไม่งั้นทำตามคำขอ PDPA ไม่ได้
            ->when(
                $request->boolean('trashed') && $request->user()?->can('forceDelete', new Customer) === true,
                fn ($q) => $q->onlyTrashed(),
            )
            ->withCount(['contacts', 'sites']);

        $this->applySort($query, $request, self::SORTABLE, 'code');

        return view('customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
            'provinces' => Customer::query()->whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
            'priceTiers' => PriceTier::options(),
            'filters' => $request->only(['q', 'price_tier', 'province', 'status', 'trashed', 'sort', 'direction']),
            'canSeeTrashed' => $request->user()?->can('forceDelete', new Customer) === true,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.create', [
            'customer' => new Customer(['branch_code' => '00000', 'credit_term_days' => 30, 'price_tier' => PriceTier::Standard, 'is_active' => true]),
            'priceTiers' => PriceTier::options(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('บันทึกลูกค้า :name แล้ว', ['name' => $customer->name_th]));
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['contacts', 'sites.primaryContact']);

        /*
         * หน้านี้แสดงชื่อ เบอร์ และอีเมลของผู้ติดต่อ = การเข้าถึงข้อมูลส่วนบุคคลตาม PDPA
         * จึงต้องบันทึกไว้ (spec 8) — ยุบเป็นวันละครั้งต่อคนต่อลูกค้า (ADR-026)
         */
        if ($customer->contacts->isNotEmpty()) {
            /** @var User $user */
            $user = $request->user();

            $this->personalData->logAccess($customer, $user);
        }

        return view('customers.show', ['customer' => $customer]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'priceTiers' => PriceTier::options(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customers->update($customer, $request->validated());

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('แก้ไขลูกค้า :name แล้ว', ['name' => $customer->name_th]));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', __('ลบลูกค้า :name แล้ว', ['name' => $customer->name_th]));
    }
}
