<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * เลย์เอาต์ของหน้าเว็บสาธารณะ — ไม่มี sidebar และไม่ต้องล็อกอิน
 */
class PublicLayout extends Component
{
    public function render(): View
    {
        return view('layouts.public');
    }
}
