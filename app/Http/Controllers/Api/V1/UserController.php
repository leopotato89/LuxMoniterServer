<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * @return AnonymousResourceCollection<UserResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->toString();

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($where) => $where
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->has('is_admin'), fn ($query) => $query->where('is_admin', $request->boolean('is_admin')))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate($this->perPage($request));

        return UserResource::collection($users);
    }

    private function sortColumn(Request $request): string
    {
        $column = $request->string('sort')->toString();

        return in_array($column, ['id', 'name', 'username', 'email', 'created_at'], true) ? $column : 'id';
    }

    private function sortDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
    }

    private function perPage(Request $request): int
    {
        return min(100, max(5, $request->integer('per_page', 10)));
    }

    public function store(Request $request): UserResource
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'is_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_admin'] = $validated['is_admin'] ?? false;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $user = User::create($validated);

        return new UserResource($user);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'is_admin' => ['boolean'],
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make((string) $validated['password']);
        } else {
            unset($validated['password']);
        }

        // Không cho tự bỏ quyền admin của mình
        if (auth()->id() === $user->id && isset($validated['is_admin']) && ! $validated['is_admin']) {
            abort(403, 'Không thể tự gỡ quyền Admin của chính mình.');
        }

        $user->update($validated);

        return new UserResource($user);
    }

    public function toggleActive(User $user): UserResource
    {
        Gate::authorize('update', $user);

        // Không cho phép tự vô hiệu hóa tài khoản của chính mình
        if (auth()->id() === $user->id) {
            abort(403, 'Không thể tự vô hiệu hóa tài khoản của chính mình.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return new UserResource($user);
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        if (auth()->id() === $user->id) {
            abort(403, 'Không thể tự xóa tài khoản của chính mình.');
        }

        $user->delete();

        return response()->noContent();
    }
}
