<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ContactLeadStatus;
use App\Enums\ServiceInterest;
use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateContactLeadRequest;
use App\Models\ContactLead;
use App\Models\User;
use App\Services\ContactLeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * หน้าให้ทีมขายตามคำขอที่ส่งเข้ามาจากหน้าเว็บ
 */
class ContactLeadController extends Controller
{
    use SortsListings;

    /** @var array<int, string> */
    private const SORTABLE = ['name', 'company', 'status', 'created_at'];

    public function __construct(private readonly ContactLeadService $leads) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ContactLead::class);

        $query = ContactLead::query()
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->with('handler:id,name');

        $this->applySort($query, $request, self::SORTABLE, 'created_at', 'desc');

        return view('leads.index', [
            'leads' => $query->paginate(20)->withQueryString(),
            'statuses' => ContactLeadStatus::options(),
            'services' => ServiceInterest::options(),
            'openCount' => ContactLead::query()->open()->count(),
            'filters' => $request->only(['q', 'status', 'open', 'sort', 'direction']),
        ]);
    }

    public function show(ContactLead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load('handler:id,name');

        return view('leads.show', [
            'lead' => $lead,
            // เสนอเฉพาะสถานะที่เปลี่ยนไปได้จริงจากสถานะปัจจุบัน
            'nextStatuses' => array_filter(
                ContactLeadStatus::cases(),
                fn (ContactLeadStatus $status): bool => $lead->status->canTransitionTo($status),
            ),
        ]);
    }

    public function update(UpdateContactLeadRequest $request, ContactLead $lead): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->filled('status')) {
            $this->leads->changeStatus($lead, ContactLeadStatus::from($request->string('status')->toString()), $user);
        }

        if ($request->has('internal_note')) {
            $this->leads->updateNote($lead, $request->string('internal_note')->toString() ?: null);
        }

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', __('บันทึกการติดตามคำขอแล้ว'));
    }

    public function destroy(Request $request, ContactLead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return redirect()
            ->route('leads.index')
            ->with('success', __('ลบคำขอของ :name แล้ว', ['name' => $lead->name]));
    }
}
