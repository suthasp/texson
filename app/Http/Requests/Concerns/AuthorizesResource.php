<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * ตรวจสิทธิ์ใน FormRequest ไม่ใช่ใน Controller
 *
 * เหตุผล: Laravel รัน authorize() ของ FormRequest **ก่อน** validation
 * ถ้าปล่อยให้ authorize() คืน true แล้วไปเช็คสิทธิ์ใน Controller ผู้ใช้ที่ไม่มีสิทธิ์
 * จะได้ 302 พร้อม validation error แทนที่จะได้ 403 — เท่ากับรั่วข้อมูลว่ากฎ validation คืออะไร
 */
trait AuthorizesResource
{
    /**
     * ชื่อคลาสโมเดลที่ใช้ตรวจสิทธิ์ 'create'
     *
     * @return class-string<Model>
     */
    abstract protected function resourceClass(): string;

    /**
     * ชื่อพารามิเตอร์ใน route ที่ผูกกับโมเดล (เช่น 'customer' ใน customers/{customer})
     */
    abstract protected function resourceRouteKey(): string;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $model = $this->route($this->resourceRouteKey());

        return $model instanceof Model
            ? $user->can('update', $model)
            : $user->can('create', $this->resourceClass());
    }
}
