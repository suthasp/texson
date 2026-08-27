<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * GET /api/v1/customers?search= (spec 6)
     *
     * ไม่ส่งผู้ติดต่อมากับรายการทั้งหน้า เพราะเป็นข้อมูลส่วนบุคคลตาม PDPA (spec 8)
     * ผู้เรียกต้องขอรายละเอียดรายคนซึ่งถูกบันทึกลง activity log
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->search($request->string('search')->toString())
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->when($request->filled('price_tier'), fn ($q) => $q->where('price_tier', $request->string('price_tier')->toString()))
            ->orderBy('code')
            ->paginate(min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load(['contacts', 'sites']));
    }
}
