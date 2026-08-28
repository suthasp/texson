@php
    // เนื้อหาทั้งหมดอยู่บนสุดเพื่อให้แก้ถ้อยคำได้โดยไม่ต้องไล่อ่าน markup
    // ข้อความไทยเป็นคีย์แปล ฉบับอังกฤษอยู่ใน lang/en.json

    $problems = [
        ['icon' => '⚡', 'title' => __('UPS ไม่เคยถูกทดสอบ'), 'body' => __('แบตเตอรี่เสื่อมโดยไม่รู้ตัว กว่าจะรู้ก็ตอนไฟดับแล้วระบบล่มทั้งบริษัท')],
        ['icon' => '🌡️', 'title' => __('แอร์เสียกลางดึก ไม่มีแผนสำรอง'), 'body' => __('อุณหภูมิพุ่ง อุปกรณ์ดับ ไม่มี SOP ว่าใครต้องทำอะไร')],
        ['icon' => '📋', 'title' => __('ไม่มีแผน PM ที่เป็นระบบ'), 'body' => __('ซ่อมเมื่อพัง (Corrective อย่างเดียว) ค่าใช้จ่ายแฝงสูงกว่าการป้องกันหลายเท่า')],
        ['icon' => '👷', 'title' => __('ทีมช่างขาดความรู้เฉพาะทาง'), 'body' => __('ดูแลอาคารทั่วไปได้ แต่ไม่เข้าใจ Critical Environment ของห้อง Server')],
    ];

    $services = [
        [
            'no' => 'SERVICE 01',
            'title' => __('Facility Audit ห้อง Server / Data Center'),
            'points' => [
                __('ตรวจประเมินระบบไฟฟ้า, UPS, เครื่องกำเนิดไฟฟ้า'),
                __('ตรวจระบบปรับอากาศ (Precision Air / Cooling)'),
                __('ระบบดับเพลิง, ควบคุมการเข้าออก, Monitoring'),
                __('ประเมินความเสี่ยง Single Point of Failure'),
                __('รายงานผล + แผนแก้ไขเรียงตามความเร่งด่วน'),
            ],
            'best' => __('องค์กรที่มีห้อง Server เอง และอยากรู้ว่า "เสี่ยงตรงไหน" ก่อนเกิดเหตุ'),
        ],
        [
            'no' => 'SERVICE 02',
            'title' => __('วางแผน Preventive Maintenance (PM)'),
            'points' => [
                __('ออกแบบแผน PM รายสัปดาห์/เดือน/ปี ทุกระบบ'),
                __('จัดทำ Checklist และ SOP มาตรฐาน Data Center'),
                __('วางขั้นตอน Corrective Maintenance และ Escalation'),
                __('กำหนด Spare Part ที่ควรมีสำรอง'),
                __('ระบบเอกสารพร้อมใช้ ส่งมอบให้ทีมของคุณ'),
            ],
            'best' => __('องค์กรที่ซ่อมเมื่อพัง และอยากเปลี่ยนมาเป็นการป้องกันเชิงระบบ'),
        ],
        [
            'no' => 'SERVICE 03',
            'title' => __('อบรมทีมช่าง / วิศวกร In-house'),
            'points' => [
                __('พื้นฐาน Critical Environment สำหรับห้อง Server'),
                __('การทำ PM ระบบไฟฟ้า UPS และระบบปรับอากาศ'),
                __('การตอบสนองเหตุฉุกเฉิน (Emergency Response)'),
                __('ฝึกจากเคสจริง ไม่ใช่แค่สไลด์'),
                __('จัดอบรม On-site ที่หน้างานของคุณ'),
            ],
            'best' => __('องค์กรที่อยากให้ทีมตัวเองดูแลได้เอง ลดการพึ่ง Vendor ภายนอก'),
        ],
    ];

    $products = [
        ['icon' => '⚡', 'title' => __('ระบบไฟฟ้าและไฟสำรอง'), 'points' => [__('UPS และแบตเตอรี่สำรอง'), __('ตู้ MDB / ตู้ไฟฟ้าย่อย'), __('ATS และเครื่องกำเนิดไฟฟ้า')]],
        ['icon' => '❄️', 'title' => __('ระบบปรับอากาศห้อง Server'), 'points' => [__('Precision Air / In-row Cooling'), __('ระบบจัดการลมร้อน–ลมเย็น (Containment)'), __('อะไหล่และชุดบำรุงรักษา')]],
        ['icon' => '🗄️', 'title' => __('ตู้ Rack และโครงสร้างพื้นฐาน'), 'points' => [__('ตู้ Rack และอุปกรณ์จัดเก็บสาย'), __('PDU และระบบจ่ายไฟในตู้'), __('Raised Floor และงานเดินสายสัญญาณ')]],
        ['icon' => '📡', 'title' => __('Monitoring และความปลอดภัย'), 'points' => [__('ระบบ Monitoring อุณหภูมิ ความชื้น น้ำรั่ว'), __('ระบบดับเพลิงสำหรับห้อง Server'), __('ระบบควบคุมการเข้าออกและกล้องวงจรปิด')]],
    ];

    $reasons = [
        ['title' => __('ประสบการณ์หน้างานจริง'), 'body' => __('ปฏิบัติงาน Operation และ Maintenance ใน Data Center จริง เห็นทุกเคสที่เกิดขึ้นจริง ไม่ใช่แค่ในตำรา')],
        ['title' => __('รายงานที่ทำตามได้จริง'), 'body' => __('ไม่ใช่รายงานหนา 100 หน้าที่อ่านไม่รู้เรื่อง แต่เป็นแผนแก้ไขที่เรียงลำดับความเร่งด่วนและงบประมาณชัดเจน')],
        ['title' => __('เป็นกลาง ไม่ขายของ'), 'body' => __('เราไม่ได้เป็นตัวแทนขายอุปกรณ์ยี่ห้อใด คำแนะนำของเราจึงยึดประโยชน์ของคุณเป็นหลัก')],
        ['title' => __('ถ่ายทอดให้ทีมคุณทำต่อได้'), 'body' => __('เป้าหมายคือให้ทีมของคุณดูแลระบบเองได้อย่างมั่นใจ ไม่ใช่ผูกคุณไว้กับเราตลอดไป')],
    ];

    $steps = [
        ['title' => __('ปรึกษาเบื้องต้น (ฟรี)'), 'body' => __('คุยปัญหาและความต้องการทางโทรศัพท์หรือออนไลน์ ~30 นาที')],
        ['title' => __('สำรวจหน้างาน'), 'body' => __('เข้าดูสถานที่จริง ประเมินขอบเขตงานและเสนอราคา')],
        ['title' => __('ดำเนินงาน'), 'body' => __('Audit / วางแผน PM / อบรม ตามขอบเขตที่ตกลง')],
        ['title' => __('ส่งมอบ + ติดตามผล'), 'body' => __('รายงานและเอกสารครบถ้วน พร้อมให้คำปรึกษาต่อเนื่อง')],
    ];
@endphp

<x-public-layout>

    {{-- ══ Hero ══ --}}
    <section class="mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 lg:px-8 lg:pb-24 lg:pt-24">
        <span class="inline-block rounded-full border border-aqua-400 px-4 py-1.5 text-sm text-navy-800">
            {{ __('ที่ปรึกษา Data Center Facility & Critical Environment') }}
        </span>

        <h1 class="mt-7 max-w-3xl text-3xl font-semibold leading-snug sm:text-4xl lg:text-5xl lg:leading-tight">
            {{ __('ห้อง Server ของคุณ พร้อมรับมือเหตุไฟดับ–ระบบล่ม') }}<span class="text-aqua-500">{{ __('แค่ไหน?') }}</span>
        </h1>

        <p class="mt-6 max-w-3xl text-base leading-relaxed text-gray-600 sm:text-lg">
            {{ __('เราตรวจประเมิน วางแผนบำรุงรักษา และอบรมทีมงานของคุณ ด้วยประสบการณ์ปฏิบัติงานจริงด้าน Facility Operation, Preventive & Corrective Maintenance ใน Data Center ระดับมืออาชีพ — เพื่อให้ระบบของคุณ "ไม่ล่ม" ไม่ใช่แค่ "ซ่อมเร็ว"') }}
        </p>

        <div class="mt-9 flex flex-wrap items-center gap-3">
            <a href="#contact"
               class="rounded-lg bg-navy-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-navy-800">
                {{ __('ปรึกษาฟรี ไม่มีค่าใช้จ่าย') }}
            </a>
            <a href="#services"
               class="rounded-lg border border-gray-300 px-6 py-3 text-sm font-semibold text-navy-800 transition hover:border-navy-900">
                {{ __('ดูบริการของเรา') }}
            </a>
        </div>

        {{-- ── ตัวเลข ── --}}
        <dl class="mt-16 grid gap-8 sm:grid-cols-3">
            @foreach ([
                ['10+ ' . __('ปี'), __('ประสบการณ์ Facility Operation จริง')],
                ['24/7', __('มุมมองจากงานเดินระบบจริง ไม่ใช่แค่ทฤษฎี')],
                ['100%', __('รายงานพร้อมแผนแก้ไขที่ทำได้จริง')],
            ] as [$value, $caption])
                <div>
                    <dt class="text-3xl font-semibold text-aqua-600">{{ $value }}</dt>
                    <dd class="mt-1 text-sm text-gray-600">{{ $caption }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ══ ปัญหาที่พบบ่อย ══ --}}
    <section class="bg-gray-50 py-16 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold sm:text-3xl">{{ __('ปัญหาเหล่านี้ คุ้นไหมครับ?') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-center text-gray-600">
                {{ __('องค์กรส่วนใหญ่ "มี" ห้อง Server แต่ไม่มีคนดูแลระบบ Facility อย่างเป็นระบบ') }}
            </p>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($problems as $problem)
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <span class="text-2xl" aria-hidden="true">{{ $problem['icon'] }}</span>
                        <h3 class="mt-4 font-semibold">{{ $problem['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $problem['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ บริการ ══ --}}
    <section id="services" class="scroll-mt-20 py-16 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold sm:text-3xl">{{ __('บริการของเรา') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-center text-gray-600">
                {{ __('ครอบคลุมตั้งแต่ตรวจประเมิน วางระบบ ไปจนถึงสร้างทีมของคุณให้ดูแลเองได้') }}
            </p>

            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach ($services as $service)
                    <div class="flex flex-col rounded-xl border border-gray-200 p-7">
                        <p class="text-xs font-semibold tracking-[0.2em] text-aqua-600">{{ $service['no'] }}</p>
                        <h3 class="mt-4 text-lg font-semibold leading-snug">{{ $service['title'] }}</h3>

                        <ul class="mt-5 space-y-3">
                            @foreach ($service['points'] as $point)
                                <li class="flex gap-3 text-sm leading-relaxed text-gray-700">
                                    <span class="mt-0.5 shrink-0 font-semibold text-aqua-600" aria-hidden="true">✓</span>
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-auto border-t border-dashed border-gray-300 pt-5 text-sm text-gray-600">
                            <span class="font-semibold text-navy-800">{{ __('เหมาะกับ:') }}</span> {{ $service['best'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ สินค้าและอุปกรณ์ ══ --}}
    <section id="products" class="scroll-mt-20 border-t border-gray-200 bg-gray-50 py-16 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold sm:text-3xl">{{ __('สินค้าและอุปกรณ์') }}</h2>
            <p class="mx-auto mt-4 max-w-3xl text-center text-gray-600">
                {{ __('จัดหาอุปกรณ์ระบบ Facility สำหรับห้อง Server โดยเลือกสเปกให้เหมาะกับหน้างานจริง ไม่ผูกกับยี่ห้อใดยี่ห้อหนึ่ง') }}
            </p>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <span class="text-2xl" aria-hidden="true">{{ $product['icon'] }}</span>
                        <h3 class="mt-4 font-semibold leading-snug">{{ $product['title'] }}</h3>

                        <ul class="mt-4 space-y-2.5">
                            @foreach ($product['points'] as $point)
                                <li class="flex gap-2.5 text-sm leading-relaxed text-gray-700">
                                    <span class="shrink-0 text-aqua-500" aria-hidden="true">›</span>
                                    <span>{{ $point }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ ทำไมต้องเรา ══ --}}
    <section id="why-us" class="scroll-mt-20 py-16 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold sm:text-3xl">{{ __('ทำไมต้องเรา') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-center text-gray-600">
                {{ __('เราไม่ใช่ที่ปรึกษาที่รู้แต่ทฤษฎี — เราคือคนที่เดินระบบจริงทุกวัน') }}
            </p>

            <div class="mt-12 grid gap-8 sm:grid-cols-2">
                @foreach ($reasons as $reason)
                    <div class="border-s-2 border-aqua-400 ps-5">
                        <h3 class="font-semibold">{{ $reason['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $reason['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══ ขั้นตอนการทำงาน ══ --}}
    <section id="process" class="scroll-mt-20 border-t border-gray-200 bg-gray-50 py-16 lg:py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-center text-2xl font-semibold sm:text-3xl">{{ __('ขั้นตอนการทำงาน') }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-center text-gray-600">
                {{ __('เริ่มต้นง่าย รู้ค่าใช้จ่ายชัดเจนก่อนตัดสินใจ') }}
            </p>

            <ol class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as $index => $step)
                    <li class="rounded-xl border border-gray-200 bg-white p-6">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 text-sm font-semibold text-white">
                            {{ $index + 1 }}
                        </span>
                        <h3 class="mt-4 font-semibold leading-snug">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ══ ติดต่อ ══ --}}
    <section id="contact" class="scroll-mt-20 py-16 lg:py-24">
        <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">

            <div>
                <h2 class="text-2xl font-semibold sm:text-3xl">{{ __('ปรึกษาฟรี ไม่มีค่าใช้จ่าย') }}</h2>
                <p class="mt-4 max-w-md leading-relaxed text-gray-600">
                    {{ __('เล่าปัญหาหรือความต้องการของคุณ เราจะติดต่อกลับภายใน 1 วันทำการ') }}
                </p>

                <dl class="mt-9 space-y-6">
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('โทรศัพท์') }}</dt>
                        <dd class="mt-1">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}"
                               class="text-lg font-medium text-aqua-600 hover:text-aqua-700">{{ $contact['phone'] }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('อีเมล') }}</dt>
                        <dd class="mt-1">
                            <a href="mailto:{{ $contact['email'] }}" class="text-lg hover:text-aqua-600">{{ $contact['email'] }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">{{ __('เวลาทำการ') }}</dt>
                        <dd class="mt-1 text-lg">{{ $contact['hours'] }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 p-6 sm:p-8">
                @if (session('success'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif

                {{--
                    error รวมด้านบน — ช่องล่อสแปมไม่มีที่แสดง error ของตัวเอง
                    ถ้า autofill ของเบราว์เซอร์เผลอกรอกให้ ผู้ใช้จริงจะเห็นว่าเกิดอะไรขึ้น
                    ไม่ใช่กดส่งแล้วเงียบหาย
                --}}
                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('landing.contact') }}" class="space-y-5">
                    @csrf

                    {{-- กับดักสแปม: บอตกรอกทุกช่องที่เจอ ส่วนคนไม่เห็นช่องนี้ --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">{{ __('เว็บไซต์') }}</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <label for="name" class="block text-sm text-gray-600">{{ __('ชื่อ–นามสกุล') }} *</label>
                        <input id="name" name="name" type="text" required maxlength="120"
                               value="{{ old('name') }}"
                               placeholder="{{ __('ชื่อของคุณ') }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 text-sm focus:border-aqua-500 focus:ring-aqua-500">
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="company" class="block text-sm text-gray-600">{{ __('บริษัท / องค์กร') }}</label>
                        <input id="company" name="company" type="text" maxlength="150"
                               value="{{ old('company') }}"
                               placeholder="{{ __('ชื่อบริษัท') }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 text-sm focus:border-aqua-500 focus:ring-aqua-500">
                        <x-input-error :messages="$errors->get('company')" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="contact" class="block text-sm text-gray-600">{{ __('เบอร์โทร / อีเมล') }} *</label>
                        <input id="contact" name="contact" type="text" required maxlength="150"
                               value="{{ old('contact') }}"
                               placeholder="{{ __('ช่องทางติดต่อกลับ') }}"
                               class="mt-1.5 w-full rounded-lg border-gray-300 text-sm focus:border-aqua-500 focus:ring-aqua-500">
                        <x-input-error :messages="$errors->get('contact')" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="service_interest" class="block text-sm text-gray-600">{{ __('บริการที่สนใจ') }}</label>
                        <select id="service_interest" name="service_interest"
                                class="mt-1.5 w-full rounded-lg border-gray-300 text-sm focus:border-aqua-500 focus:ring-aqua-500">
                            @foreach ($serviceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('service_interest') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('service_interest')" class="mt-1.5" />
                    </div>

                    <div>
                        <label for="message" class="block text-sm text-gray-600">{{ __('รายละเอียดเพิ่มเติม') }}</label>
                        <textarea id="message" name="message" rows="4" maxlength="2000"
                                  placeholder="{{ __('เล่าปัญหาหรือสิ่งที่ต้องการคร่าวๆ') }}"
                                  class="mt-1.5 w-full rounded-lg border-gray-300 text-sm focus:border-aqua-500 focus:ring-aqua-500">{{ old('message') }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-1.5" />
                    </div>

                    <button type="submit"
                            class="w-full rounded-lg bg-navy-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-navy-800">
                        {{ __('ส่งข้อความ') }}
                    </button>

                    <p class="text-center text-xs leading-relaxed text-gray-500">
                        {{ __('ข้อมูลที่กรอกใช้เพื่อติดต่อกลับเท่านั้น และเก็บตามนโยบายคุ้มครองข้อมูลส่วนบุคคล') }}
                    </p>
                </form>
            </div>
        </div>
    </section>

</x-public-layout>
