<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuotationUpdateRequest;
use App\Http\Requests\QuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Mail\QuotationMail;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationPdfService;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/**
 * ใบเสนอราคาผ่าน REST API (spec 6)
 *
 * ทุก action เรียก QuotationService ตัวเดียวกับหน้าเว็บ กฎธุรกิจจึงไม่มีทางแตกเป็นสองชุด
 * การเปลี่ยนสถานะข้ามขั้นถูกโยนเป็น InvalidStatusTransitionException แล้วถูกแปลงเป็น 409
 * ที่ bootstrap/app.php
 */
class QuotationController extends Controller
{
    /** ความสัมพันธ์ที่ต้องมีเสมอเวลาส่งใบรายเดียวกลับไป */
    private const DETAIL_RELATIONS = ['items', 'customer:id,code,name_th', 'contact', 'site', 'salesUser:id,name', 'approver:id,name'];

    public function __construct(
        private readonly QuotationService $quotations,
        private readonly QuotationPdfService $pdf,
    ) {}

    /**
     * GET /api/v1/quotations?status=&customer_id=&from=&to=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->with(['customer:id,code,name_th', 'salesUser:id,name'])
            ->withCount('items')
            // sales เห็นเฉพาะใบของตัวเอง — กรองที่ query ไม่ใช่ตอนเรนเดอร์ (spec 8)
            ->visibleTo($request->user())
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('issue_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('issue_date', '<=', $request->date('to')))
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin($request->integer('expiring_days', 7)))
            ->when($request->boolean('exclude_superseded'), fn ($q) => $q->whereNull('superseded_at'))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 25), 100))
            ->withQueryString();

        return QuotationResource::collection($quotations);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);

        return $this->resource($quotation);
    }

    public function store(QuotationRequest $request): JsonResponse
    {
        $quotation = $this->quotations->createDraft($request->validated());

        return $this->resource($quotation)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/v1/quotations/{id} — เฉพาะ draft
     *
     * ใบที่ส่งไปแล้วจะได้ 409 จาก service ไม่ใช่ 403 (ดู QuotationUpdateRequest)
     */
    public function update(QuotationUpdateRequest $request, Quotation $quotation): QuotationResource
    {
        return $this->resource($this->quotations->updateDraft($quotation, $request->validated()));
    }

    public function destroy(Quotation $quotation): Response
    {
        $this->authorize('delete', $quotation);

        $quotation->delete();

        return response()->noContent();
    }

    // ── การเปลี่ยนสถานะ ─────────────────────────────────────

    public function submit(Quotation $quotation): QuotationResource
    {
        $this->authorize('submitAny', $quotation);

        return $this->resource($this->quotations->submit($quotation));
    }

    public function approve(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('approveAny', $quotation);

        /** @var User $user */
        $user = $request->user();

        return $this->resource($this->quotations->approve($quotation, $user));
    }

    /**
     * POST /api/v1/quotations/{id}/send
     *
     * ส่งอีเมลพร้อม PDF แนบเมื่อระบุ email มาด้วย ไม่งั้นแค่บันทึกว่าส่งแล้ว
     * (บางดีลส่งทาง LINE หรือยื่นเอกสารตัวจริง)
     */
    public function send(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('sendAny', $quotation);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'locale' => ['nullable', 'in:th,en'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->quotations->send($quotation);

        if (filled($data['email'] ?? null)) {
            Mail::to($data['email'])->send(new QuotationMail(
                $quotation->fresh(['items', 'customer', 'contact', 'salesUser']),
                $data['locale'] ?? 'th',
                $data['note'] ?? null,
            ));
        }

        return $this->resource($quotation)->additional([
            'meta' => ['emailed_to' => $data['email'] ?? null],
        ]);
    }

    public function accept(Quotation $quotation): QuotationResource
    {
        $this->authorize('decideAny', $quotation);

        return $this->resource($this->quotations->accept($quotation));
    }

    public function reject(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('decideAny', $quotation);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->resource($this->quotations->reject($quotation, $data['reason'] ?? null));
    }

    public function cancel(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('updateAny', $quotation);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->resource($this->quotations->cancel($quotation, $data['reason'] ?? null));
    }

    /**
     * POST /api/v1/quotations/{id}/revise → revision ใหม่
     */
    public function revise(Quotation $quotation): QuotationResource
    {
        $this->authorize('reviseAny', $quotation);

        $revision = $this->quotations->revise($quotation);

        return $this->resource($revision)->additional([
            'meta' => ['superseded_quotation_id' => $quotation->id],
        ]);
    }

    /**
     * GET /api/v1/quotations/{id}/pdf?lang=th|en
     */
    public function pdf(Request $request, Quotation $quotation): Response
    {
        $this->authorize('print', $quotation);

        $locale = $request->string('lang')->toString() === 'en' ? 'en' : 'th';

        return $this->pdf->render($quotation, $locale)
            ->download($this->pdf->filename($quotation, $locale));
    }

    private function resource(Quotation $quotation): QuotationResource
    {
        return new QuotationResource($quotation->load(self::DETAIL_RELATIONS));
    }
}
