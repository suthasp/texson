<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * หน้าตั้งค่าระบบ — ข้อมูลบริษัทที่ขึ้นหัวใบเสนอราคา ค่าเริ่มต้นเอกสาร และเกณฑ์อนุมัติ
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Setting::class);

        $groups = SettingKey::groups();
        $active = $request->string('group')->toString();

        if (! array_key_exists($active, $groups)) {
            $active = array_key_first($groups);
        }

        return view('settings.index', [
            'groups' => $groups,
            'active' => $active,
            'keys' => SettingKey::inGroup($active),
            'values' => $this->settings->group($active),
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $values = $request->knownValues();

        // ไฟล์อัปโหลดถูกตั้งชื่อสุ่มและเก็บใน storage/app/private (spec 8)
        foreach (['logo' => SettingKey::CompanyLogoPath, 'signature' => SettingKey::CompanySignaturePath] as $field => $key) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $previous = $this->settings->string($key);
            $values[$key->value] = $request->file($field)->store('branding', 'private');

            if ($previous !== '' && Storage::disk('private')->exists($previous)) {
                Storage::disk('private')->delete($previous);
            }
        }

        $this->settings->setMany($values);

        return redirect()
            ->route('settings.index', ['group' => $request->string('group')->toString()])
            ->with('success', __('บันทึกค่าตั้งแล้ว'));
    }

    /**
     * ส่งไฟล์ในโฟลเดอร์ private ผ่าน controller ที่ตรวจสิทธิ์ ไม่เปิด symlink ออก public (spec 8)
     */
    public function asset(string $key): StreamedResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settingKey = SettingKey::tryFrom($key);

        abort_if($settingKey === null || ! $settingKey->isFile(), 404);

        $path = $this->settings->string($settingKey);

        abort_if($path === '' || ! Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path);
    }
}
