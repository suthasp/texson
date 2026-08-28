@props(['on' => 'light', 'size' => 'md'])

{{--
    โลโก้ TEXSON จาก public/logo

    ชื่อไฟล์บอก "พื้นหลังที่เอาไปวาง" ไม่ใช่สีของตัวอักษร —
    logo-dark.png ตัวอักษรขาวสำหรับพื้นเข้ม · logo-light.png ตัวอักษรกรมท่าสำหรับพื้นสว่าง

    ความสูงมาจาก prop ไม่ใช่ class ที่ส่งเข้ามา เพราะ $attributes->merge() ต่อ class เพิ่ม
    ไม่ได้ทับของเดิม พอมี h-6 กับ h-8 ติดมาด้วยกัน ตัวที่ชนะคือตัวที่อยู่หลังกว่าใน CSS
    ซึ่งไม่ใช่ตัวที่คนเรียกตั้งใจ

    ไม่ใส่ self-* เพราะจะไปชนกับ align-self ที่คนเรียกตั้งไว้ (เจอมาแล้วบนหน้าเข้าสู่ระบบ)
    ใช้ object-contain แทน — ถ้า flex ยืดกล่องออก รูปก็ยังคงสัดส่วนเดิมอยู่ในกล่อง ไม่บิด
--}}
@php
    $height = match ($size) {
        'sm' => 'h-5',
        'lg' => 'h-9',
        default => 'h-6',
    };
@endphp

<img src="{{ asset('logo/logo-'.($on === 'dark' ? 'dark' : 'light').'.png') }}"
     alt="TEXSON"
     width="408" height="101"
     {{ $attributes->merge(['class' => $height.' w-auto max-w-full object-contain']) }}>
