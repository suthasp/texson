<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use SortsListings;

    /** @var array<int, string> */
    private const SORTABLE = ['code', 'name', 'lead_time_days', 'created_at'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'))
            ->withCount('products');

        $this->applySort($query, $request, self::SORTABLE, 'code');

        return view('suppliers.index', [
            'suppliers' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status', 'sort', 'direction']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create', ['supplier' => new Supplier(['is_active' => true, 'lead_time_days' => 0])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('บันทึกผู้ขาย :name แล้ว', ['name' => $supplier->name]));
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load('products.category');

        return view('suppliers.show', ['supplier' => $supplier]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('แก้ไขผู้ขาย :name แล้ว', ['name' => $supplier->name]));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('ลบผู้ขาย :name แล้ว', ['name' => $supplier->name]));
    }
}
