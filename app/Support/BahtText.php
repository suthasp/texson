<?php

declare(strict_types=1);

namespace App\Support;

/**
 * แปลงจำนวนเงินเป็นตัวอักษรภาษาไทย (spec 4.2)
 *
 *   0            → ศูนย์บาทถ้วน
 *   0.25         → ยี่สิบห้าสตางค์
 *   1000000      → หนึ่งล้านบาทถ้วน
 *   1234567.89   → หนึ่งล้านสองแสนสามหมื่นสี่พันห้าร้อยหกสิบเจ็ดบาทแปดสิบเก้าสตางค์
 *
 * กฎการอ่านที่ต้องระวัง
 *  - หลักสิบที่เป็น 1 อ่านว่า "สิบ" ไม่ใช่ "หนึ่งสิบ"
 *  - หลักสิบที่เป็น 2 อ่านว่า "ยี่สิบ"
 *  - หลักหน่วยที่เป็น 1 และมีหลักอื่นนำหน้า อ่านว่า "เอ็ด"
 *  - จำนวนเกินเจ็ดหลักอ่านเป็นกลุ่มละหกหลักคั่นด้วย "ล้าน"
 */
final class BahtText
{
    /** @var array<int, string> */
    private const DIGITS = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];

    /** @var array<int, string> ชื่อหลักจากขวาไปซ้ายภายในกลุ่มหกหลัก */
    private const PLACES = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    public static function convert(string|int|float $amount): string
    {
        $normalized = Money::normalize($amount);
        $isNegative = Money::isNegative($normalized);
        $absolute = $isNegative ? substr($normalized, 1) : $normalized;

        [$bahtPart, $satangPart] = array_pad(explode('.', $absolute, 2), 2, '00');

        $baht = self::readInteger($bahtPart);
        $satang = (int) str_pad($satangPart, 2, '0');

        $text = match (true) {
            // ต่ำกว่าหนึ่งบาท — อ่านเฉพาะสตางค์ ไม่ต้องมีคำว่า "ศูนย์บาท" นำหน้า
            $bahtPart === '0' && $satang > 0 => self::readInteger((string) $satang).'สตางค์',
            $satang === 0 => $baht.'บาทถ้วน',
            default => $baht.'บาท'.self::readInteger((string) $satang).'สตางค์',
        };

        return $isNegative ? 'ลบ'.$text : $text;
    }

    /**
     * อ่านจำนวนเต็มเป็นข้อความไทย
     */
    private static function readInteger(string $number): string
    {
        $number = ltrim($number, '0');

        if ($number === '') {
            return self::DIGITS[0];
        }

        return self::readWithMillions($number, isLeading: true);
    }

    /**
     * แตกจำนวนยาวเป็นกลุ่มละหกหลักแล้วคั่นด้วย "ล้าน"
     *
     * @param  bool  $isLeading  กลุ่มนี้เป็นกลุ่มซ้ายสุดของทั้งจำนวนหรือไม่
     */
    private static function readWithMillions(string $number, bool $isLeading): string
    {
        if (strlen($number) > 6) {
            $head = substr($number, 0, strlen($number) - 6);
            $tail = substr($number, -6);

            return self::readWithMillions($head, $isLeading).'ล้าน'
                .self::readWithMillions($tail, isLeading: false);
        }

        return self::readGroup($number, $isLeading);
    }

    /**
     * อ่านกลุ่มไม่เกินหกหลัก
     */
    private static function readGroup(string $group, bool $isLeading): string
    {
        $group = ltrim($group, '0');

        if ($group === '') {
            return '';
        }

        // กลุ่มที่ตามหลัง "ล้าน" และมีค่าเท่ากับหนึ่ง อ่านว่า "เอ็ด" เช่น 1,000,001 = หนึ่งล้านเอ็ด
        if (! $isLeading && $group === '1') {
            return 'เอ็ด';
        }

        $length = strlen($group);
        $text = '';

        for ($index = 0; $index < $length; $index++) {
            $digit = (int) $group[$index];
            $place = $length - $index - 1;

            if ($digit === 0) {
                continue;
            }

            $text .= match (true) {
                $place === 1 && $digit === 1 => self::PLACES[1],
                $place === 1 && $digit === 2 => 'ยี่'.self::PLACES[1],
                $place === 0 && $digit === 1 && $length > 1 => 'เอ็ด',
                default => self::DIGITS[$digit].self::PLACES[$place],
            };
        }

        return $text;
    }
}
