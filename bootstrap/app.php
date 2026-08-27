<?php

declare(strict_types=1);

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        /*
         * ทุก request ใต้ /api ถูกบังคับให้เป็น JSON
         * ไม่งั้น client ที่ลืมส่ง Accept header จะได้ HTML ของหน้า login แทน 401
         */
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Exception ของกฎธุรกิจ (ของไม่พอ / เปลี่ยนสถานะข้ามขั้น) ไม่ใช่ error 500
         *
         * ฝั่งเว็บ: เด้งกลับหน้าเดิมพร้อมข้อความ ผู้ใช้แก้แล้วลองใหม่ได้
         * ฝั่ง API: ตอบ status ตามที่ exception กำหนด พร้อมรายละเอียด (spec 6)
         */
        $exceptions->render(function (DomainException $e, Request $request): ?Response {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    ...$e->context(),
                ], $e->httpStatus());
            }

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        });

        /*
         * ── รูปแบบ error ของ API ให้คงที่ (spec 6) ──
         * ผู้เรียกต้องอ่าน message ได้จากที่เดียวเสมอ ไม่ว่าจะพลาดเรื่องอะไร
         */

        $exceptions->render(function (AuthenticationException $e, Request $request): ?Response {
            return $request->expectsJson()
                ? response()->json(['message' => __('ต้องยืนยันตัวตนก่อน — แนบ header Authorization: Bearer {token}')], 401)
                : null;
        });

        /*
         * ดักที่ AccessDeniedHttpException ไม่ใช่ AuthorizationException
         * เพราะ Laravel แปลง exception ของ policy เป็น HTTP exception ตั้งแต่ก่อนเรียก render callback
         */
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): ?Response {
            return $request->expectsJson()
                ? response()->json(['message' => __('คุณไม่มีสิทธิ์ทำรายการนี้')], 403)
                : null;
        });

        // ModelNotFound จาก route model binding มาถึงตรงนี้เป็น NotFoundHttpException
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?Response {
            if (! $request->expectsJson()) {
                return null;
            }

            $missingModel = $e->getPrevious() instanceof ModelNotFoundException
                ? class_basename($e->getPrevious()->getModel())
                : null;

            return response()->json([
                'message' => $missingModel === null
                    ? __('ไม่พบ endpoint ที่เรียก')
                    : __('ไม่พบ :model ที่ระบุ', ['model' => $missingModel]),
            ], 404);
        });
    })->create();
