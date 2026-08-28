@props(['on' => 'light'])

{{--
    โลโก้ TEXSON จาก public/logo

    ชื่อไฟล์บอก "พื้นหลังที่เอาไปวาง" ไม่ใช่สีของตัวอักษร —
    logo-dark.png เป็นตัวอักษรขาวสำหรับพื้นเข้ม · logo-light.png เป็นตัวอักษรกรมท่าสำหรับพื้นสว่าง
--}}
<img src="{{ asset('logo/logo-'.($on === 'dark' ? 'dark' : 'light').'.png') }}"
     alt="TEXSON"
     {{ $attributes->merge(['class' => 'h-8 w-auto']) }}>
