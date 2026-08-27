<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| งานตามเวลา
|--------------------------------------------------------------------------
*/

// ใบเสนอราคาที่เลยวันยืนราคาต้องเปลี่ยนเป็นหมดอายุทุกเช้า 06:00 (spec 4.3)
Schedule::command('quotations:expire')
    ->dailyAt('06:00')
    ->timezone('Asia/Bangkok')
    ->withoutOverlapping();
