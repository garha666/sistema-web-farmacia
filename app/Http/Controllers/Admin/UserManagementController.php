<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserManagementController extends Controller
{
    private const ROLES = [User::ROLE_TECNICO, User::ROLE_BODEGUERO, User::ROLE_QUIMICO];

    public function index(): View
    {
        return view('admin.users.index', [
            'roles' => self::ROLES,
            'users' => User::query()->orderBy('name')->orderBy('id')->paginate(20),
            'navGroup' => 'admin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }

        $values = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['bail', 'required', 'string', 'min:12', 'confirmed', static function ($attribute, string $value, $fail): void {
                if (strlen($value) > 72 || str_contains($value, "\0")) {
                    $fail(__('users.password_bytes'));
                }
            }],
            'role' => ['required', Rule::in(self::ROLES)],
        ], [], __('users.fields'));

        DB::transaction(function () use ($values): void {
            $user = new User;
            $user->fill(collect($values)->only(['name', 'email', 'password'])->all());
            $user->assignRole($values['role']);
        });

        return to_route('admin.users.index')->with('user_created', true);
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless(in_array($target->role, self::ROLES, true), 403);

            if ($target->disabled_at !== null) {
                return;
            }

            if (CashRegister::query()->where('user_id', $target->id)->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['deactivate' => __('users.cash_open')]);
            }

            $target->forceFill(['disabled_at' => now(), 'remember_token' => null])->save();

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'auth.user_deactivated',
                'auditable_type' => $target->getMorphClass(),
                'auditable_id' => $target->id,
            ]);
        }, 3);

        return to_route('admin.users.index')->with('user_deactivated', true);
    }
}
