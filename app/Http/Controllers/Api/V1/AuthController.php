<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ออกและเพิกถอน Sanctum token
 *
 * สเปกข้อ 6 กำหนดว่า API ใช้ Sanctum token แต่ไม่ได้ระบุ endpoint ที่ออก token
 * ถ้าไม่มีก็เรียก API ไม่ได้เลย จึงเพิ่มไว้ใต้ /auth และคุมด้วย throttle 5 ครั้ง/นาที
 * ตามข้อกำหนด rate limit ของการล็อกอินใน spec 8
 */
class AuthController extends Controller
{
    public function token(IssueTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        // ข้อความเดียวกันทั้งกรณีอีเมลผิดและรหัสผ่านผิด — ไม่บอกใบ้ว่าอีเมลไหนมีอยู่จริง
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ'),
            ]);
        }

        // token ชื่อซ้ำจากเครื่องเดิมถูกล้างก่อน กัน token ค้างสะสมเมื่อผู้ใช้ล็อกอินซ้ำ
        $user->tokens()->where('name', $data['device_name'])->delete();

        $token = $user->createToken($data['device_name']);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'device_name' => $data['device_name'],
                'user' => $this->profile($user),
            ],
            'meta' => [
                'token_type' => 'Bearer',
                'expires_at' => config('sanctum.expiration') === null
                    ? null
                    : now()->addMinutes((int) config('sanctum.expiration'))->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * ข้อมูลผู้ใช้ปัจจุบันพร้อมสิทธิ์ — client ใช้ตัดสินใจว่าจะโชว์เมนูอะไร
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->profile($user),
            'meta' => [
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            ],
        ]);
    }

    /**
     * เพิกถอนเฉพาะ token ที่ใช้เรียกครั้งนี้ ไม่แตะเครื่องอื่นของผู้ใช้คนเดียวกัน
     */
    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => ['revoked' => true],
            'meta' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'roles' => $user->getRoleNames()->all(),
        ];
    }
}
