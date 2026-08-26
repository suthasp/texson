<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Uom;
use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use SortsListings;

    /** @var array<int, string> */
    private const SORTABLE = ['sku', 'name_th', 'model', 'list_price', 'cost_price', 'created_at'];

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()
            ->with(['category', 'brand'])
            ->search($request->string('q')->toString())
            ->inCategory($request->integer('category_id') ?: null)
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->boolean('serialized'), fn ($q) => $q->where('is_serialized', true));

        $this->applySort($query, $request, self::SORTABLE, 'sku');

        return view('products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'categories' => Category::query()->with('children')->roots()->orderBy('sort_order')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'canViewCost' => $request->user()->can('viewCost', Product::class),
            'filters' => $request->only(['q', 'category_id', 'brand_id', 'status', 'serialized', 'sort', 'direction']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', $this->formData(new Product(['uom' => Uom::Pcs, 'is_active' => true])));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->products->create($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('บันทึกสินค้า :sku แล้ว', ['sku' => $product->sku]));
    }

    public function show(Request $request, Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category.parent', 'brand', 'suppliers']);

        return view('products.show', [
            'product' => $product,
            'canViewCost' => $request->user()->can('viewCost', Product::class),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load('suppliers');

        return view('products.edit', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->products->update($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('แก้ไขสินค้า :sku แล้ว', ['sku' => $product->sku]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', __('ลบสินค้า :sku แล้ว', ['sku' => $product->sku]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::query()->with('parent')->orderBy('parent_id')->orderBy('sort_order')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'allSuppliers' => Supplier::query()->active()->orderBy('name')->get(),
            'uoms' => Uom::options(),
        ];
    }
}
