<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RoleName;
use App\Http\Controllers\Concerns\SortsListings;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use SortsListings;

    /** @var array<int, string> */
    private const SORTABLE = ['employee_code', 'name', 'email', 'last_login_at', 'created_at'];

    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with('roles')
            ->search($request->string('q')->toString())
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'));

        $this->applySort($query, $request, self::SORTABLE, 'name');

        return view('users.index', [
            'users' => $query->paginate(20)->withQueryString(),
            'roles' => RoleName::options(),
            'filters' => $request->only(['q', 'role', 'status', 'sort', 'direction']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => RoleName::options(),
            'assignedRoles' => [],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create($request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', __('สร้างผู้ใช้ :name แล้ว', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => RoleName::options(),
            'assignedRoles' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update($user, $request->validated());

        return redirect()
            ->route('users.index')
            ->with('success', __('แก้ไขผู้ใช้ :name แล้ว', ['name' => $user->name]));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', __('ลบผู้ใช้ :name แล้ว', ['name' => $user->name]));
    }
}
