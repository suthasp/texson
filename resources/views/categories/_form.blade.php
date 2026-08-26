@php
    /** @var App\Models\Category $category */
    $isEdit = $category->exists;
    $parentOptions = $parents->pluck('name_th', 'id')->all();
@endphp

<form method="POST" action="{{ $isEdit ? route('categories.update', $category) : route('categories.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="name_th" :label="__('ชื่อหมวด (ไทย)')" :value="$category->name_th" required />
            <x-form.input name="name_en" :label="__('ชื่อหมวด (อังกฤษ)')" :value="$category->name_en" />
            <x-form.select name="parent_id" :label="__('หมวดแม่')" :options="$parentOptions" :selected="$category->parent_id"
                           :placeholder="__('— เป็นหมวดหลัก —')"
                           :help="__('เว้นว่างไว้ถ้าต้องการให้เป็นหมวดระดับบนสุด')" />
            <x-form.input name="sort_order" :label="__('ลำดับการแสดง')" :value="$category->sort_order ?? 0" type="number" min="0" max="9999" required />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('categories.index')" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกหมวดหมู่') }}
        </button>
    </div>
</form>
