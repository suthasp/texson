<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouses) {}

    public function index(): View
    {
        $this->authorize('viewAny', Warehouse::class);

        return view('warehouses.index', [
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Warehouse::class);

        return view('warehouses.create', ['warehouse' => new Warehouse(['is_active' => true])]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouses->create($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('บันทึกคลัง :name แล้ว', ['name' => $warehouse->name]));
    }

    public function edit(Warehouse $warehouse): View
    {
        $this->authorize('update', $warehouse);

        return view('warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $this->warehouses->update($warehouse, $request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('แก้ไขคลัง :name แล้ว', ['name' => $warehouse->name]));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        if ($warehouse->is_default) {
            return back()->with('error', __('ลบคลังเริ่มต้นไม่ได้ — ตั้งคลังอื่นเป็นคลังเริ่มต้นก่อน'));
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', __('ลบคลัง :name แล้ว', ['name' => $warehouse->name]));
    }
}
