<?php

declare(strict_types=1);

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * รูปแบบร่วมของไฟล์ Excel ทุกชุด (spec 5)
 *
 * ตั้งฟอนต์เป็น Sarabun ทั้งชีต — Excel บนเครื่องที่ไม่มีฟอนต์ไทยดี ๆ
 * จะเรนเดอร์สระบน–ล่างเพี้ยนเหมือนปัญหาเดียวกับ PDF ใน Phase 3
 */
trait ThaiSheet
{
    /**
     * แถวหัวตารางเป็นสีแบรนด์และตรึงไว้ให้เลื่อนดูข้อมูลยาว ๆ ได้
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Sarabun')->setSize(11);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1B2A4A'],
            ],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        return [];
    }

    /**
     * ชื่อไฟล์พร้อมวันเวลาที่ออกรายงาน — กันไฟล์เก่าใหม่ปนกันในโฟลเดอร์ดาวน์โหลด
     */
    protected function stampedFilename(string $prefix): string
    {
        return sprintf('%s_%s.xlsx', $prefix, now()->format('Ymd_His'));
    }
}
