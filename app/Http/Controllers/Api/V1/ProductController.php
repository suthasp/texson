<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockLevelResource;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * GET /api/v1/products?search=&category_id=&low_stock=1&page= (spec 6)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category:id,name_th', 'brand:id,name', 'stockLevels'])
            ->search($request->string('search')->toString())
            ->inCategory($request->filled('category_id') ? $request->integer('category_id') : null)
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            // เหลือน้อยตัดสินจาก stock_levels ไม่ใช่ products จึงต้องกรองผ่าน subquery
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereIn(
                'id',
                StockLevel::query()->belowMinimum()->select('stock_levels.product_id'),
            ))
            ->orderBy('sku')
            ->paginate(min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load(['category:id,name_th', 'brand:id,name', 'stockLevels']));
    }

    /**
     * GET /api/v1/products/{id}/stock — ยอดคงเหลือแยกตามคลัง (spec 6)
     */
    public function stock(Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);
        $this->authorize('viewAny', StockLevel::class);

        // load ลงบนโมเดลจริง เพื่อให้ totalOnHand()/totalAvailable() อ่าน relation เดียวกันได้
        $product->load('stockLevels.warehouse:id,code,name');

        // ให้ isBelowMinimum() อ่าน min_stock ได้โดยไม่ยิง query ต่อแถว
        $levels = $product->stockLevels
            ->each(fn (StockLevel $level) => $level->setRelation('product', $product));

        return StockLevelResource::collection($levels)->additional([
            'meta' => [
                'product' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name_th' => $product->name_th,
                    'min_stock' => number_format((float) $product->min_stock, 3, '.', ''),
                ],
                'total_on_hand' => $product->totalOnHand(),
                'total_available' => $product->totalAvailable(),
            ],
        ]);
    }
}
