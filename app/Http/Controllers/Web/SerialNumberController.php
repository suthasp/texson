<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Models\SerialNumber;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ทะเบียน serial — Phase 1 ของโรดแมปใช้ดูอย่างเดียว
 * การเปลี่ยนสถานะเกิดจากเอกสาร (รับเข้า / โอน / ส่งของใน Phase 4)
 */
class SerialNumberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SerialNumber::class);

        $serials = SerialNumber::query()
            ->with(['product', 'warehouse', 'customer'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->boolean('expiring'), fn ($q) => $q
                ->whereNotNull('warranty_end')
                ->whereBetween('warranty_end', [now()->toDateString(), now()->addDays(90)->toDateString()]))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('serial-numbers.index', [
            'serials' => $serials,
            'warehouses' => Warehouse::query()->orderBy('code')->get(),
            'statuses' => SerialStatus::options(),
            'filters' => $request->only(['q', 'status', 'warehouse_id', 'expiring']),
        ]);
    }
}
