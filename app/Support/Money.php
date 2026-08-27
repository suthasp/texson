<?php

declare(strict_types=1);

namespace App\Support;

/**
 * คำนวณเงินด้วย bcmath ทั้งหมด (กฎเหล็กข้อ 4 — ห้ามใช้ float)
 *
 * ทุกเมธอดรับและคืนค่าเป็น string เสมอ ห้ามแปลงเป็น float ระหว่างทาง
 * เพราะ 0.1 + 0.2 ใน float ได้ 0.30000000000000004 แล้วยอดท้ายบิลจะเพี้ยนทีละสตางค์
 */
final class Money
{
    /** ทศนิยมของเงิน */
    public const SCALE = 2;

    /** ทศนิยมของจำนวนสินค้า */
    public const QTY_SCALE = 3;

    /** ทศนิยมระหว่างทาง — เผื่อไว้ให้ปัดครั้งเดียวตอนจบ */
    private const WORK_SCALE = 8;

    /**
     * ปัดครึ่งขึ้น (spec 4.2) — 1.005 → 1.01
     *
     * bcadd ตัดทศนิยมทิ้งแบบ truncate อยู่แล้ว การบวก 0.005 ก่อนตัดจึงได้ผลเท่ากับปัดครึ่งขึ้น
     * ค่าติดลบปัดออกจากศูนย์ในทางเดียวกัน เพื่อให้ยอดคืนเงินสมมาตรกับยอดขาย
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bccomp($value, '0', self::WORK_SCALE) < 0
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }

    public static function add(string ...$values): string
    {
        return array_reduce(
            $values,
            static fn (string $carry, string $value): string => bcadd($carry, $value, self::SCALE),
            '0.00',
        );
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * คูณแล้วปัดเป็นสตางค์ — ใช้กับ จำนวน × ราคาต่อหน่วย
     */
    public static function multiply(string $a, string $b): string
    {
        return self::round(bcmul($a, $b, self::WORK_SCALE));
    }

    /**
     * หาเปอร์เซ็นต์ของยอด เช่น VAT 7% หรือส่วนลด 15%
     */
    public static function percentOf(string $base, string $percent): string
    {
        return self::round(bcdiv(bcmul($base, $percent, self::WORK_SCALE), '100', self::WORK_SCALE));
    }

    /**
     * คิดว่า part เป็นกี่เปอร์เซ็นต์ของ base — คืน '0.00' เมื่อ base เป็นศูนย์ (กันหารด้วยศูนย์)
     */
    public static function percentage(string $part, string $base): string
    {
        if (self::isZero($base)) {
            return '0.00';
        }

        return self::round(bcmul(bcdiv($part, $base, self::WORK_SCALE), '100', self::WORK_SCALE));
    }

    public static function isZero(string $value): bool
    {
        return bccomp($value, '0', self::WORK_SCALE) === 0;
    }

    public static function isNegative(string $value): bool
    {
        return bccomp($value, '0', self::WORK_SCALE) < 0;
    }

    public static function greaterThan(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) > 0;
    }

    public static function lessThan(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) < 0;
    }

    /**
     * ไม่ให้ค่าติดลบหลุดไปเป็นยอดเงิน เช่น ส่วนลดมากกว่ามูลค่าบรรทัด
     */
    public static function clampToZero(string $value): string
    {
        return self::isNegative($value) ? '0.00' : self::normalize($value);
    }

    /**
     * ทำให้เป็นสตริงเงินมาตรฐาน 2 ตำแหน่ง — ใช้ก่อนบันทึกลง DB ทุกครั้ง
     *
     * ปัดครึ่งขึ้น ไม่ใช่ตัดทิ้ง เพราะราคาที่ผู้ใช้พิมพ์มาเกิน 2 ตำแหน่ง (เช่น 1.005)
     * ต้องได้ค่าเดียวกับที่หน้าจอโชว์ ไม่งั้นยอดรวมจะไม่ตรงกับที่ลูกค้าเห็น
     */
    public static function normalize(mixed $value, int $scale = self::SCALE): string
    {
        $raw = is_string($value) ? trim($value) : (string) $value;

        if ($raw === '' || ! is_numeric($raw)) {
            return number_format(0, $scale, '.', '');
        }

        // ตัดสัญกรณ์วิทยาศาสตร์ทิ้ง — bcmath อ่าน '1.0E+5' ไม่ออก
        if (stripos($raw, 'e') !== false) {
            $raw = number_format((float) $raw, $scale + 4, '.', '');
        }

        return self::round($raw, $scale);
    }

    /**
     * ทำให้เป็นสตริงจำนวนสินค้ามาตรฐาน 3 ตำแหน่ง
     */
    public static function normalizeQty(mixed $value): string
    {
        return self::normalize($value, self::QTY_SCALE);
    }

    /**
     * จัดรูปแบบสำหรับแสดงผล — มีคอมมาคั่นหลักพัน
     */
    public static function format(string $value, int $scale = self::SCALE): string
    {
        return number_format((float) $value, $scale);
    }
}
