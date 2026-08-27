<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\ConvertQuotationToSalesOrder;
use App\Enums\QuotationStatus;
use App\Enums\SettingKey;
use App\Http\Controllers\Concerns\ProvidesQuotationFormData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertQuotationRequest;
use App\Http\Requests\QuotationDecisionRequest;
use App\Http\Requests\QuotationRequest;
use App\Http\Requests\QuotationSendRequest;
use App\Mail\QuotationMail;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class QuotationController extends Controller
{
    use ProvidesQuotationFormData;

    public function __construct(
        private readonly QuotationService $quotations,
        private readonly QuotationPdfService $pdf,
        private readonly SettingService $settings,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->with(['customer:id,code,name_th', 'salesUser:id,name'])
            ->withCount('items')
            // sales เห็นเฉพาะใบของตัวเอง — กรองที่ query ไม่ใช่ที่ view (spec 8)
            ->visibleTo($request->user())
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->date('to')))
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin(7))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'statuses' => QuotationStatus::options(),
            'filters' => $request->only(['q', 'status', 'customer_id', 'from', 'to', 'expiring']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quotation::class);

        $quotation = new Quotation($this->quotations->defaultsFor());
        $quotation->setRelation('items', collect());

        return view('quotations.create', [
            'quotation' => $quotation,
            ...$this->quotationFormData(),
        ]);
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        $quotation = $this->quotations->createDraft($request->validated());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', __('สร้างใบเสนอราคา :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        $quotation->load(['items.product.stockLevels', 'customer', 'contact', 'site', 'salesUser', 'creator', 'approver', 'parent', 'revisions', 'salesOrder']);

        return view('quotations.show', [
            'quotation' => $quotation,
            'approvalReasons' => $this->quotations->approvalReasons($quotation),
            'minMargin' => $this->settings->decimal(SettingKey::ApprovalMinMarginPercent),
            // ใช้ในกล่องสร้างใบสั่งขาย — ต้องเลือกคลังที่จะจองของ (ADR-017)
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        $this->authorize('update', $quotation);

        $quotation->load('items.product');

        return view('quotations.edit', [
            'quotation' => $quotation,
            ...$this->quotationFormData(),
        ]);
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->updateDraft($quotation, $request->validated());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', __('บันทึกใบ :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $this->authorize('delete', $quotation);

        $quotation->delete();

        return redirect()
            ->route('quotations.index')
            ->with('success', __('ลบใบ :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    // ── การเปลี่ยนสถานะ ─────────────────────────────────────

    public function submit(Quotation $quotation): RedirectResponse
    {
        $this->authorize('submit', $quotation);

        $this->quotations->submit($quotation);

        return back()->with('success', __('ส่งใบ :no เข้าคิวรออนุมัติแล้ว', ['no' => $quotation->displayNo()]));
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('approve', $quotation);

        /** @var User $user */
        $user = $request->user();

        $this->quotations->approve($quotation, $user);

        return back()->with('success', __('อนุมัติใบ :no แล้ว — ส่งให้ลูกค้าได้', ['no' => $quotation->displayNo()]));
    }

    /**
     * ตีกลับให้ฝ่ายขายแก้
     */
    public function returnToDraft(QuotationDecisionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->returnToDraft($quotation, $request->validated()['reason'] ?? null);

        return back()->with('success', __('ตีกลับใบ :no ให้แก้ไขแล้ว', ['no' => $quotation->displayNo()]));
    }

    public function send(QuotationSendRequest $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validated();

        $this->quotations->send($quotation);

        if ($request->boolean('send_email')) {
            Mail::to($data['email'])->send(new QuotationMail(
                $quotation->fresh(['items', 'customer', 'contact', 'salesUser']),
                $data['locale'] ?? 'th',
                $data['note'] ?? null,
            ));

            return back()->with('success', __('ส่งใบ :no ทางอีเมลถึง :email แล้ว', [
                'no' => $quotation->displayNo(),
                'email' => $data['email'],
            ]));
        }

        return back()->with('success', __('บันทึกว่าส่งใบ :no ให้ลูกค้าแล้ว', ['no' => $quotation->displayNo()]));
    }

    public function accept(Quotation $quotation): RedirectResponse
    {
        $this->authorize('decide', $quotation);

        $this->quotations->accept($quotation);

        return back()->with('success', __('บันทึกว่าลูกค้าตอบรับใบ :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    public function reject(QuotationDecisionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->reject($quotation, $request->validated()['reason'] ?? null);

        return back()->with('success', __('บันทึกว่าลูกค้าปฏิเสธใบ :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    public function cancel(QuotationDecisionRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->quotations->cancel($quotation, $request->validated()['reason'] ?? null);

        return back()->with('success', __('ยกเลิกใบ :no แล้ว', ['no' => $quotation->displayNo()]));
    }

    /**
     * สร้างฉบับแก้ไข — ใบเดิมยังอยู่ครบและถูกประทับว่าถูกแทนที่แล้ว
     */
    public function revise(Quotation $quotation): RedirectResponse
    {
        $this->authorize('revise', $quotation);

        $revision = $this->quotations->revise($quotation);

        return redirect()
            ->route('quotations.edit', $revision)
            ->with('success', __('สร้างฉบับแก้ไข :no แล้ว — ใบเดิมถูกเก็บไว้เป็นประวัติ', [
                'no' => $revision->displayNo(),
            ]));
    }

    /**
     * แปลงเป็นใบสั่งขาย — ทำได้ครั้งเดียวต่อใบ (spec 4.3)
     */
    public function convertToSalesOrder(
        ConvertQuotationRequest $request,
        Quotation $quotation,
        ConvertQuotationToSalesOrder $convert,
    ): RedirectResponse {
        $data = $request->validated();

        if ($request->hasFile('customer_po_file')) {
            // ตั้งชื่อสุ่มและเก็บใน storage/app/private (spec 8)
            $data['customer_po_file'] = $request->file('customer_po_file')->store('customer-po', 'private');
        }

        $order = $convert->handle($quotation, $data);

        if (isset($data['customer_po_file'])) {
            $order->update(['customer_po_file' => $data['customer_po_file']]);
        }

        return redirect()
            ->route('sales-orders.show', $order)
            ->with('success', __('สร้างใบสั่งขาย :no จากใบเสนอราคา :quote แล้ว — กดยืนยันเพื่อจองของ', [
                'no' => $order->so_no,
                'quote' => $quotation->displayNo(),
            ]));
    }

    /**
     * พิมพ์ PDF ไทย/อังกฤษ (spec 5)
     */
    public function pdf(Request $request, Quotation $quotation): Response
    {
        $this->authorize('print', $quotation);

        $locale = $request->string('lang')->toString() === 'en' ? 'en' : 'th';
        $pdf = $this->pdf->render($quotation, $locale);
        $filename = $this->pdf->filename($quotation, $locale);

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
