@php
    $messages = array_filter([
        'success' => session('success'),
        // ทำสำเร็จแต่มีเรื่องต้องรู้ เช่น จองของได้ไม่ครบแล้วกลายเป็น backorder
        'warning' => session('warning'),
        'error' => session('error'),
        'status' => session('status'),
    ]);

    $styles = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'status' => 'border-aqua-200 bg-aqua-50 text-aqua-800',
    ];
@endphp

@forelse ($messages as $type => $message)
    <div x-data="{ shown: true }" x-show="shown" x-transition
         role="{{ $type === 'error' ? 'alert' : 'status' }}"
         class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm {{ $styles[$type] }}">
        <span class="flex-1">{{ $message }}</span>
        <button type="button" @click="shown = false" class="shrink-0 opacity-60 transition hover:opacity-100" aria-label="{{ __('ปิด') }}">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
@empty
@endforelse

@if ($errors->any() && ! $errors->has('_form_only'))
    <div role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        {{ __('กรอกข้อมูลไม่ครบหรือไม่ถูกต้อง :count จุด — ดูรายละเอียดใต้ช่องที่มีปัญหา', ['count' => $errors->count()]) }}
    </div>
@endif
