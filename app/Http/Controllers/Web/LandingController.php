<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ServiceInterest;
use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactLeadRequest;
use App\Services\ContactLeadService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * หน้าเว็บสาธารณะ — หน้าเดียวที่คนนอกองค์กรเข้าถึงได้โดยไม่ต้องล็อกอิน (ADR-029)
 *
 * ข้อมูลติดต่อดึงจากตารางตั้งค่า ผู้ดูแลระบบจึงแก้เบอร์/อีเมลได้เองโดยไม่ต้องแก้โค้ด
 */
class LandingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly ContactLeadService $leads,
    ) {}

    public function index(): View
    {
        return view('landing.index', [
            'serviceOptions' => ServiceInterest::options(),
            'contact' => [
                'phone' => $this->settings->string(SettingKey::CompanyPhone) ?: '099-989-8888',
                'email' => $this->settings->string(SettingKey::CompanyEmail) ?: 'support@texson.co.th',
                'hours' => __('จันทร์–เสาร์ 9:00–18:00 น.'),
            ],
        ]);
    }

    /**
     * รับคำขอจากฟอร์ม "ปรึกษาฟรี"
     *
     * เด้งกลับไปที่ #contact เพื่อให้ผู้ติดต่อเห็นข้อความยืนยันตรงจุดที่เพิ่งกรอก
     * ไม่ใช่เด้งขึ้นหัวหน้าแล้วต้องเลื่อนหาเอง
     */
    public function contact(ContactLeadRequest $request): RedirectResponse
    {
        $this->leads->record($request->lead());

        return redirect()
            ->to(route('landing').'#contact')
            ->with('success', __('ได้รับข้อความแล้ว เราจะติดต่อกลับภายใน 1 วันทำการ'));
    }
}
