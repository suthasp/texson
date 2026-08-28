@props(['action', 'confirm' => null, 'label' => null])

{{-- ยืนยันก่อนทุก destructive action (spec 7) --}}
<form method="POST" action="{{ $action }}" class="inline"
      x-data
      @submit.prevent="confirm(@js($confirm ?? __('ยืนยันการลบ? การกระทำนี้ย้อนกลับไม่ได้'))) && $el.submit()">
    @csrf
    @method('DELETE')
    <button type="submit"
            {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md border border-rose-200 bg-white px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-1']) }}>
        {{ $label ?? __('ลบ') }}
    </button>
</form>
