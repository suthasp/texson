<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ErasePersonalDataRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\PersonalDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * หน้าจัดการข้อมูลส่วนบุคคลของลูกค้ารายคน ตาม PDPA (spec 8)
 *
 * รองรับสิทธิ์ของเจ้าของข้อมูลสองข้อที่สเปกระบุไว้:
 * สิทธิ์ขอเข้าถึง/รับสำเนา (ส่งออกเป็นไฟล์) และสิทธิ์ขอให้ลบ (ล้างข้อมูลส่วนบุคคล)
 */
class CustomerPersonalDataController extends Controller
{
    public function __construct(private readonly PersonalDataService $personalData) {}

    /**
     * สรุปว่าระบบเก็บอะไรของลูกค้ารายนี้ไว้บ้าง และใครเข้าถึงไปแล้ว
     */
    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('export', $customer);

        /** @var User $user */
        $user = $request->user();

        $this->personalData->logAccess($customer, $user);

        return view('customers.personal-data', [
            'customer' => $customer,
            'data' => $this->personalData->export($customer),
            'accessLog' => $this->personalData->accessLog($customer, 50),
            'canErase' => $user->can('forceDelete', $customer),
            'deletableOutright' => $this->personalData->canBeDeletedOutright($customer),
        ]);
    }

    /**
     * ไฟล์สำเนาข้อมูลสำหรับส่งให้เจ้าของข้อมูล
     *
     * เป็น JSON เพราะคำขอตาม PDPA ต้องส่งมอบในรูปแบบที่อ่านและนำไปใช้ต่อได้ด้วยเครื่อง
     */
    public function download(Request $request, Customer $customer): Response
    {
        $this->authorize('export', $customer);

        /** @var User $user */
        $user = $request->user();

        $this->personalData->logExport($customer, $user);

        $json = json_encode(
            $this->personalData->export($customer),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $filename = 'personal-data_'.$customer->code.'_'.now()->format('Ymd_His').'.json';

        return response((string) $json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * ลบข้อมูลส่วนบุคคลตามคำขอ
     *
     * ลูกค้าที่ยังไม่มีเอกสารเลย ลบทิ้งทั้งแถวได้ ส่วนลูกค้าที่มีใบเสนอราคาหรือใบสั่งขายแล้ว
     * ต้องเก็บแถวไว้ให้เอกสารภาษีอ้างถึงได้ จึงล้างเฉพาะฟิลด์ที่เป็นข้อมูลส่วนบุคคล (ADR-024)
     */
    public function erase(ErasePersonalDataRequest $request, Customer $customer): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reason = $request->string('reason')->toString();

        if ($this->personalData->canBeDeletedOutright($customer)) {
            // ล้างข้อมูลก่อนแล้วค่อยลบแถว เพื่อให้ activity log บันทึกได้ว่าเคยมีอะไรอยู่
            // ถ้าลบแถวก่อน subject จะหายไปแล้วเขียน log ไม่ได้
            $this->personalData->anonymize($customer, $user, $reason);
            $customer->forceDelete();

            return redirect()
                ->route('customers.index')
                ->with('success', __('ลบข้อมูลลูกค้า :code ออกจากระบบถาวรแล้ว', ['code' => $customer->code]));
        }

        $this->personalData->anonymize($customer, $user, $reason);

        return redirect()
            ->route('customers.personal-data', $customer)
            ->with('success', __('ลบข้อมูลส่วนบุคคลของ :code แล้ว เอกสารภาษีที่อ้างถึงลูกค้ารายนี้ยังอยู่ครบ', [
                'code' => $customer->code,
            ]));
    }

    /**
     * กู้ลูกค้าที่ถูกลบแบบ soft delete กลับมา
     *
     * ลูกค้าที่ถูกล้างข้อมูลตามคำขอ PDPA แล้วกู้ไม่ได้ เพราะข้อมูลเดิมไม่เหลืออยู่ให้กู้
     */
    public function restore(Customer $customer): RedirectResponse
    {
        $this->authorize('restore', $customer);

        abort_if($customer->isAnonymized(), 403, __('ลูกค้ารายนี้ถูกลบข้อมูลส่วนบุคคลตามคำขอ PDPA แล้ว กู้คืนไม่ได้'));

        $customer->restore();

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', __('กู้ข้อมูลลูกค้า :name กลับมาแล้ว', ['name' => $customer->name_th]));
    }
}
