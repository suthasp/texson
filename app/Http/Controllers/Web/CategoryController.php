<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('categories.index', [
            'roots' => Category::query()
                ->with(['children' => fn ($q) => $q->withCount('products')])
                ->withCount('products')
                ->roots()
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('categories.create', [
            'category' => new Category(['sort_order' => 0]),
            'parents' => Category::query()->roots()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', __('บันทึกหมวด :name แล้ว', ['name' => $category->name_th]));
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('categories.edit', [
            'category' => $category,
            // หมวดแม่เลือกได้เฉพาะหมวดระดับบน และต้องไม่ใช่ตัวเอง (โครงสร้างลึกสุด 2 ระดับ)
            'parents' => Category::query()->roots()->whereKeyNot($category->id)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', __('แก้ไขหมวด :name แล้ว', ['name' => $category->name_th]));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            return back()->with('error', __('ลบหมวด :name ไม่ได้ เพราะยังมีสินค้าอยู่ในหมวดนี้', ['name' => $category->name_th]));
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', __('ลบหมวด :name แล้ว', ['name' => $category->name_th]));
    }
}
