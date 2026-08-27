<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * อ่าน/เขียนค่าตั้งระบบ (spec 3.4)
 *
 * ค่าถูก cache ไว้ทั้งชุดเพราะทุกหน้าที่ออกเอกสารต้องอ่าน VAT และข้อมูลบริษัท
 * การเขียนทุกครั้งล้าง cache ทันที ไม่ใช้ TTL เพื่อไม่ให้ค่าเก่าค้างหลังผู้ดูแลกดบันทึก
 */
class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, static fn (): array => Setting::query()
            ->pluck('value', 'key')
            ->all());
    }

    public function get(SettingKey $key): string|int|float|bool|null
    {
        $value = $this->all()[$key->value] ?? null;

        if ($value === null || $value === '') {
            return $key->default();
        }

        return is_scalar($value) ? $value : $key->default();
    }

    /**
     * อ่านเป็นข้อความ — ใช้กับหัวเอกสารที่ต้องได้ string เสมอ
     */
    public function string(SettingKey $key, string $fallback = ''): string
    {
        $value = $this->get($key);

        return $value === null ? $fallback : (string) $value;
    }

    /**
     * อ่านเป็นสตริงตัวเลขสำหรับ bcmath — ห้ามคืน float
     */
    public function decimal(SettingKey $key): string
    {
        $value = (string) ($this->get($key) ?? '0');

        return is_numeric($value) ? $value : '0';
    }

    public function integer(SettingKey $key): int
    {
        return (int) ($this->get($key) ?? 0);
    }

    public function set(SettingKey $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key->value],
            ['value' => $value, 'group' => $key->group()],
        );

        $this->flush();
    }

    /**
     * บันทึกหลายค่าในทรานแซกชันเดียว — หน้าตั้งค่าส่งมาทั้งกลุ่ม
     *
     * @param  array<string, mixed>  $values  คีย์เป็นค่าของ SettingKey
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $rawKey => $value) {
                $key = SettingKey::tryFrom((string) $rawKey);

                // คีย์ที่ไม่รู้จักถูกทิ้ง ไม่ใช่บันทึกไว้ — กันค่าที่ปลอมมาจากฟอร์ม
                if ($key === null) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key->value],
                    ['value' => $value, 'group' => $key->group()],
                );
            }
        });

        $this->flush();
    }

    /**
     * ค่าทั้งกลุ่มพร้อม default สำหรับคีย์ที่ยังไม่เคยตั้ง
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function group(string $group): array
    {
        $result = [];

        foreach (SettingKey::inGroup($group) as $key) {
            $result[$key->value] = $this->get($key);
        }

        return $result;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
