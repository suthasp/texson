<x-mail::message>
# {{ __('คำขอติดต่อใหม่') }}

{{ __('มีคำขอติดต่อเข้ามาจากหน้าเว็บ เมื่อ :at', ['at' => $lead->created_at?->translatedFormat('j F Y เวลา H:i น.')]) }}

**{{ __('ชื่อ–นามสกุล') }}:** {{ $lead->name }}
@if ($lead->company)
**{{ __('บริษัท / องค์กร') }}:** {{ $lead->company }}
@endif
**{{ __('เบอร์โทร / อีเมล') }}:** {{ $lead->contact }}
@if ($lead->service_interest)
**{{ __('บริการที่สนใจ') }}:** {{ $lead->service_interest->label() }}
@endif

@if ($lead->message)
**{{ __('รายละเอียดเพิ่มเติม') }}**

{{ $lead->message }}
@endif

<x-mail::button :url="route('leads.show', $lead)">
{{ __('เปิดในระบบ') }}
</x-mail::button>

{{ __('ตอบกลับภายใน 1 วันทำการตามที่แจ้งไว้บนหน้าเว็บ') }}
</x-mail::message>
