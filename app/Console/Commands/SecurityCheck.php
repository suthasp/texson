<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * ตรวจ checklist ความปลอดภัยข้อ 8 ของสเปกกับเครื่องที่กำลังรันอยู่จริง
 *
 * เทสต์พิสูจน์ว่า "โค้ดทำถูก" ส่วนคำสั่งนี้พิสูจน์ว่า "เครื่องที่ deploy ตั้งค่าถูก"
 * ซึ่งเป็นคนละเรื่องกัน และเป็นเรื่องที่พังบ่อยกว่า
 *
 * ใช้ก่อนขึ้น production ทุกครั้ง: php artisan texson:security-check
 */
class SecurityCheck extends Command
{
    protected $signature = 'texson:security-check
                            {--production : ตรวจด้วยเกณฑ์ของ production แม้ตอนนี้จะรันบนเครื่อง dev}';

    protected $description = 'ตรวจ checklist ความปลอดภัยตามสเปกข้อ 8';

    /** @var array<int, array{name: string, ok: bool, detail: string, hard: bool}> */
    private array $results = [];

    public function handle(): int
    {
        $strict = $this->option('production') || app()->isProduction();

        $this->components->info($strict
            ? 'ตรวจด้วยเกณฑ์ production'
            : 'ตรวจด้วยเกณฑ์ dev — ใช้ --production เพื่อดูว่าจะผ่านตอนขึ้นจริงหรือไม่');

        $this->checkDebugMode($strict);
        $this->checkAppKey();
        $this->checkSession($strict);
        $this->checkEnvFiles();
        $this->checkSecurityHeaders();
        $this->checkRoutesAreProtected();
        $this->checkPrivateStorage();
        $this->checkPasswordPolicy();

        $this->newLine();

        foreach ($this->results as $result) {
            $result['ok']
                ? $this->components->twoColumnDetail('<fg=green>ผ่าน</>  '.$result['name'], $result['detail'])
                : $this->components->twoColumnDetail(
                    ($result['hard'] ? '<fg=red>ไม่ผ่าน</>' : '<fg=yellow>เตือน</>').' '.$result['name'],
                    $result['detail'],
                );
        }

        $failures = array_filter($this->results, fn (array $r): bool => ! $r['ok'] && $r['hard']);

        $this->newLine();

        if ($failures !== []) {
            $this->components->error(sprintf('ไม่ผ่าน %d ข้อ — ห้ามขึ้น production จนกว่าจะแก้ครบ', count($failures)));

            return self::FAILURE;
        }

        $this->components->info('ผ่านทุกข้อที่บังคับ');

        return self::SUCCESS;
    }

    private function checkDebugMode(bool $strict): void
    {
        $debug = (bool) config('app.debug');

        $this->record(
            'APP_DEBUG ปิดอยู่',
            $strict ? ! $debug : true,
            match (true) {
                ! $debug => 'ปิดอยู่ ผู้ใช้เห็นแค่ข้อความกลาง ส่วนรายละเอียดอยู่ใน log ฝั่งเซิร์ฟเวอร์',
                $strict => 'เปิดอยู่ — จะเปิดเผย stack trace และค่าใน .env ให้คนนอกเห็น',
                default => 'เปิดอยู่ (ยอมรับได้ตอน dev) แต่ต้องปิดก่อนขึ้น production',
            },
        );
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        $this->record(
            'APP_KEY ถูกตั้งแล้ว',
            $key !== '',
            $key === '' ? 'ยังว่าง — session และข้อมูลที่เข้ารหัสไว้จะอ่านไม่ออก' : 'ตั้งแล้ว',
        );
    }

    private function checkSession(bool $strict): void
    {
        $this->record(
            'session ตั้งค่า httponly',
            (bool) config('session.http_only'),
            'JavaScript อ่าน cookie ของ session ไม่ได้',
        );

        $this->record(
            'session ตั้งค่า same_site=strict',
            config('session.same_site') === 'strict',
            'ปัจจุบัน: '.(string) config('session.same_site'),
        );

        $secure = (bool) config('session.secure');

        $this->record(
            'session ตั้งค่า secure cookie',
            $strict ? $secure : true,
            $secure
                ? 'cookie ส่งเฉพาะบน HTTPS'
                : 'ยังไม่เปิด — บน production ต้องตั้ง SESSION_SECURE_COOKIE=true (ตอน dev บน http ปิดไว้ได้)',
        );

        $this->record(
            'session ถูกเข้ารหัส',
            (bool) config('session.encrypt'),
            'ปัจจุบัน: '.((bool) config('session.encrypt') ? 'เปิด' : 'ปิด'),
            hard: false,
        );
    }

    private function checkEnvFiles(): void
    {
        $gitignore = File::exists(base_path('.gitignore'))
            ? (string) File::get(base_path('.gitignore'))
            : '';

        $this->record(
            '.env อยู่ใน .gitignore',
            str_contains($gitignore, '.env'),
            'ไฟล์ที่มี credential ต้องไม่ถูก commit',
        );

        $this->record(
            'มี .env.example ครบทุก key ที่ .env ใช้',
            ...$this->compareEnvKeys(),
        );
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function compareEnvKeys(): array
    {
        if (! File::exists(base_path('.env')) || ! File::exists(base_path('.env.example'))) {
            return [false, 'หาไฟล์ .env หรือ .env.example ไม่เจอ'];
        }

        $keys = fn (string $path): array => collect(explode("\n", (string) File::get($path)))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#') && str_contains($line, '='))
            ->map(fn (string $line): string => strtok($line, '='))
            ->unique()
            ->values()
            ->all();

        $missing = array_diff($keys(base_path('.env')), $keys(base_path('.env.example')));

        return $missing === []
            ? [true, 'ครบ']
            : [false, 'ขาดใน .env.example: '.implode(', ', $missing)];
    }

    private function checkSecurityHeaders(): void
    {
        $registered = in_array(SecurityHeaders::class, app('router')->getMiddleware(), true)
            || $this->middlewareIsGlobal();

        $this->record(
            'security header middleware ถูกผูกไว้',
            $registered,
            $registered
                ? 'X-Frame-Options / X-Content-Type-Options / Referrer-Policy / CSP'
                : 'ยังไม่ได้ผูก SecurityHeaders ใน bootstrap/app.php',
        );
    }

    private function middlewareIsGlobal(): bool
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

        return $kernel instanceof \Illuminate\Foundation\Http\Kernel
            && $kernel->hasMiddleware(SecurityHeaders::class);
    }

    /**
     * ทุก route ของเว็บต้องอยู่หลัง auth ยกเว้นหน้า login/reset และ health check
     */
    private function checkRoutesAreProtected(): void
    {
        /*
         * เทียบด้วย URI ไม่ใช่ชื่อ route เพราะ POST /login ของ Breeze ไม่มีชื่อ
         * ถ้าเทียบด้วยชื่อจะได้ค่าว่างซึ่งอ่านไม่รู้เรื่องเวลาตรวจ
         */
        $allowed = [
            // หน้าเว็บสาธารณะและฟอร์มติดต่อ — ตั้งใจให้เข้าได้โดยไม่ล็อกอิน (ADR-029)
            '/', 'contact',
            'login', 'logout', 'register',
            'forgot-password', 'reset-password', 'reset-password/{token}',
            'sanctum/csrf-cookie',
        ];

        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('web', $route->gatherMiddleware(), true))
            ->reject(fn ($route): bool => in_array('auth', $route->gatherMiddleware(), true))
            ->reject(fn ($route): bool => in_array($route->uri(), $allowed, true))
            ->map(fn ($route): string => $route->methods()[0].' /'.ltrim($route->uri(), '/'))
            ->unique()
            ->values();

        $this->record(
            'ทุกหน้าของระบบอยู่หลัง auth',
            $unprotected->isEmpty(),
            $unprotected->isEmpty() ? 'ผ่าน' : 'เปิดถึงได้โดยไม่ล็อกอิน: '.$unprotected->implode(', '),
        );

        $unthrottled = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'api/'))
            ->reject(fn ($route): bool => collect($route->gatherMiddleware())
                ->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'throttle')))
            ->map(fn ($route): string => (string) $route->uri())
            ->values();

        $this->record(
            'ทุก endpoint ของ API มี rate limit',
            $unthrottled->isEmpty(),
            $unthrottled->isEmpty() ? 'throttle ครบ' : 'ไม่มี throttle: '.$unthrottled->implode(', '),
        );
    }

    private function checkPrivateStorage(): void
    {
        $root = (string) config('filesystems.disks.private.root');

        $this->record(
            'ไฟล์แนบเก็บนอก public',
            $root !== '' && ! str_contains(str_replace('\\', '/', $root), '/public'),
            $root === '' ? 'ยังไม่ได้ตั้ง disk ชื่อ private' : $root,
        );

        $linked = File::exists(public_path('storage'));

        $this->record(
            'ไม่มี symlink storage เปิดออก public',
            ! $linked,
            $linked
                ? 'พบ public/storage — ไฟล์ใน storage/app/public เข้าถึงได้โดยไม่ตรวจสิทธิ์'
                : 'ไฟล์ทุกไฟล์ต้องผ่าน controller ที่ตรวจสิทธิ์',
            hard: false,
        );
    }

    private function checkPasswordPolicy(): void
    {
        $rule = \Illuminate\Validation\Rules\Password::default();

        $validator = validator(['password' => 'sh0rt'], ['password' => $rule]);

        $this->record(
            'รหัสผ่านอย่างน้อย 10 ตัว',
            $validator->fails(),
            'รหัสสั้นถูกปฏิเสธ',
        );
    }

    private function record(string $name, bool $ok, string $detail, bool $hard = true): void
    {
        $this->results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail, 'hard' => $hard];
    }
}
