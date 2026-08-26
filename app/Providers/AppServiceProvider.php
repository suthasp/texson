<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePasswordPolicy();

        // วันที่ในหน้าจอและ PDF ต้องเป็นภาษาไทยเมื่อ locale เป็น th
        Date::setLocale(config('app.locale'));
    }

    /**
     * รหัสผ่านอย่างน้อย 10 ตัว และต้องไม่เคยหลุดจากเหตุข้อมูลรั่ว (spec 8)
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }
}
