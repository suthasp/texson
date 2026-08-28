<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PersonalDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * หน้าอ่าน audit trail (spec 8)
 *
 * สเปกบังคับให้ทุกการเปลี่ยนสถานะเอกสาร ปรับสต็อก และแก้ราคา มีบันทึกพร้อมค่าก่อน/หลัง
 * ถ้าไม่มีหน้าอ่าน ข้อกำหนดนั้นก็พิสูจน์ไม่ได้ตอนถูกตรวจ
 *
 * อ่านอย่างเดียวเสมอ — ไม่มีทางแก้หรือลบผ่านหน้าจอ ไม่งั้น audit trail ก็เชื่อถือไม่ได้
 */
class ActivityLogController extends Controller
{
    /** ตัวเลือกช่วงเวลาในตัวกรอง (วัน) */
    private const RANGES = [7, 30, 90];

    public function index(Request $request): View
    {
        $this->authorize(PermissionName::ActivityViewAny->value);

        $validated = $request->validate([
            'log' => ['nullable', 'string', 'max:50'],
            'event' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'causer_id' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'in:'.implode(',', self::RANGES)],
        ]);

        $days = (int) ($validated['days'] ?? 30);

        $activities = Activity::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->when($validated['log'] ?? null, fn ($query, $log) => $query->where('log_name', $log))
            ->when($validated['event'] ?? null, fn ($query, $event) => $query->where('event', $event))
            ->when($validated['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($validated['causer_id'] ?? null, fn ($query, $id) => $query->where('causer_id', $id))
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'filters' => $validated + ['days' => $days],
            'ranges' => self::RANGES,
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'events' => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()->values(),
            'subjectTypes' => Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type')->filter()->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'pdpaLog' => PersonalDataService::LOG,
        ]);
    }
}
