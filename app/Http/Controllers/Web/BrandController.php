<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        return view('brands.index', [
            'brands' => Brand::query()
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%'))
                ->withCount('products')
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'filters' => $request->only(['q']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Brand::class);

        return view('brands.create', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $this->authorize('create', Brand::class);

        $brand = Brand::create($request->validated());

        return redirect()
            ->route('brands.index')
            ->with('success', __('บันทึกยี่ห้อ :name แล้ว', ['name' => $brand->name]));
    }

    public function edit(Brand $brand): View
    {
        $this->authorize('update', $brand);

        return view('brands.edit', ['brand' => $brand]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $brand->update($request->validated());

        return redirect()
            ->route('brands.index')
            ->with('success', __('แก้ไขยี่ห้อ :name แล้ว', ['name' => $brand->name]));
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        if ($brand->products()->exists()) {
            return back()->with('error', __('ลบยี่ห้อ :name ไม่ได้ เพราะยังมีสินค้าใช้ยี่ห้อนี้อยู่', ['name' => $brand->name]));
        }

        $brand->delete();

        return redirect()
            ->route('brands.index')
            ->with('success', __('ลบยี่ห้อ :name แล้ว', ['name' => $brand->name]));
    }
}
