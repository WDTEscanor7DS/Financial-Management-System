<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly UserPolicy $policy) {}

    public function index()
    {
        $users = User::with(['department', 'role'])->orderBy('name')->get();

        return response()->json(['data' => $users->map($this->transform(...))]);
    }

    public function store(UserStoreRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                ...$request->safe()->except('password'),
                'password' => Hash::make($request->input('password')),
                'status' => $request->input('status', 'Active'),
                'email_verified_at' => now(),
            ]);

            AuditService::log('User Created', 'Security & Audit', (string) $user->id, "Created account for {$user->name}.");

            return $user;
        });

        return response()->json(['data' => $this->transform($user)], 201);
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        // Section 34: an Administrator can never demote or deactivate the
        // last remaining active Administrator through this endpoint.
        $wouldDowngrade = (array_key_exists('role_id', $data) && (int) $data['role_id'] !== $user->role_id)
            || (array_key_exists('status', $data) && $data['status'] !== 'Active');

        if ($wouldDowngrade && $this->policy->wouldRemoveLastAdministrator($user)) {
            throw ValidationException::withMessages([
                'role_id' => 'This is the last active Administrator account and cannot be demoted or disabled.',
            ]);
        }

        DB::transaction(function () use ($user, $data) {
            $before = $user->only(['name', 'email', 'department_id', 'role_id', 'status']);
            $user->update($data);
            AuditService::log(
                'User Updated',
                'Security & Audit',
                (string) $user->id,
                "Updated account for {$user->name}.",
                oldValues: $before,
                newValues: $user->only(['name', 'email', 'department_id', 'role_id', 'status'])
            );
        });

        return response()->json(['data' => $this->transform($user->fresh(['department', 'role']))]);
    }

    /**
     * Section 36: an admin never sets a new password directly. This
     * dispatches Laravel's standard password-reset email/token flow for
     * the target account instead.
     */
    public function sendPasswordReset(User $user)
    {
        Password::sendResetLink(['email' => $user->email]);
        AuditService::log('Password Reset Requested', 'Security & Audit', (string) $user->id, "Password reset link sent for {$user->name}.");

        return response()->json(['message' => 'Password reset link sent.']);
    }

    public function destroy(User $user)
    {
        if ($this->policy->wouldRemoveLastAdministrator($user)) {
            throw ValidationException::withMessages([
                'user' => 'This is the last active Administrator account and cannot be removed.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $id = $user->id;
            $name = $user->name;
            $user->delete(); // soft delete -- preserves history behind FKs
            AuditService::log('User Disabled', 'Security & Audit', (string) $id, "Removed account for {$name}.");
        });

        return response()->json(status: 204);
    }

    private function transform(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'department' => $u->department?->name,
            'departmentId' => $u->department_id,
            'role' => $u->role->name,
            'roleId' => $u->role_id,
            'status' => $u->status,
            'lastLoginAt' => optional($u->last_login_at)->toDateTimeString(),
        ];
    }
}
