<x-app-layout>
    <x-slot name="title">{{ __('หมวดหมู่') }}</x-slot>

    <x-page-header :title="__('หมวดหมู่สินค้า')" :subtitle="__('โครงสร้าง 2 ระดับ — หมวดหลักและหมวดย่อย')">
        <x-slot name="actions">
            @can('create', App\Models\Category::class)
                <x-link-button :href="route('categories.create')">{{ __('เพิ่มหมวดหมู่') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2">
        @forelse ($roots as $root)
            <x-card :padded="false">
                <x-slot name="header">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-navy-900">{{ $root->name_th }}</h2>
                        @if ($root->name_en)
                            <p class="truncate text-xs text-gray-500">{{ $root->name_en }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="tabular text-xs text-gray-400">{{ $root->products_count }} {{ __('รายการ') }}</span>
                        @can('update', $root)
                            <a href="{{ route('categories.edit', $root) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                        @endcan
                    </div>
                </x-slot>

                @forelse ($root->children as $child)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-2.5 text-sm last:border-0">
                        <div class="min-w-0">
                            <p class="truncate text-gray-900">{{ $child->name_th }}</p>
                            @if ($child->name_en)
                                <p class="truncate text-xs text-gray-400">{{ $child->name_en }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="tabular text-xs text-gray-400">{{ $child->products_count }}</span>
                            @can('update', $child)
                                <a href="{{ route('categories.edit', $child) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                            @endcan
                            @can('delete', $child)
                                <x-delete-button :action="route('categories.destroy', $child)"
                                                 :confirm="__('ยืนยันการลบหมวด :name?', ['name' => $child->name_th])" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-3 text-sm text-gray-400">{{ __('ไม่มีหมวดย่อย') }}</p>
                @endforelse
            </x-card>
        @empty
            <div class="sm:col-span-2">
                <x-card><x-empty-state :message="__('ยังไม่มีหมวดหมู่')" /></x-card>
            </div>
        @endforelse
    </div>
</x-app-layout>
