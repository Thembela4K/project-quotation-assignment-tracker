<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->with(['department', 'invitations' => fn ($query) => $query->latest()])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['role' => User::ROLE_DEPARTMENT_USER, 'is_active' => true]),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request, UserInvitationService $invitations): RedirectResponse
    {
        $data = $this->validatedForInvite($request);
        $data['name'] = ($data['name'] ?? null) ?: $this->nameFromEmail($data['email']);
        $data['username'] = ($data['username'] ?? null) ?: null;
        $data['password'] = Str::password(24);
        $data['is_active'] = false;
        $data['receives_submissions'] = $request->boolean('receives_submissions');
        $data['can_access_sppra'] = $request->boolean('can_access_sppra');

        $user = User::query()->create($data);
        $invitation = $invitations->invite($user, $request->user());

        $message = $invitation->status === UserInvitation::STATUS_EMAIL_FAILED
            ? 'User invitation was created, but the email could not be sent. Check SMTP settings and resend.'
            : 'Invitation sent.';

        return redirect()->route('users.index')->with($invitation->status === UserInvitation::STATUS_EMAIL_FAILED ? 'warning' : 'success', $message);
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (! $data['password']) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function resendInvitation(Request $request, User $user, UserInvitationService $invitations): RedirectResponse
    {
        if (! $user->email) {
            return back()->with('warning', 'This user has no email address to invite.');
        }

        if ($user->invitation_accepted_at) {
            return back()->with('warning', 'This user has already accepted an invitation.');
        }

        $invitation = $invitations->resend($user, $request->user());

        return back()->with(
            $invitation->status === UserInvitation::STATUS_EMAIL_FAILED ? 'warning' : 'success',
            $invitation->status === UserInvitation::STATUS_EMAIL_FAILED
                ? 'Invitation regenerated, but the email could not be sent. Check SMTP settings.'
                : 'Invitation resent.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('warning', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required_without:email', 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required_without:username', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'department_id' => ['nullable', Rule::requiredIf($request->input('role') === User::ROLE_DEPARTMENT_USER), 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
            'receives_submissions' => ['nullable', 'boolean'],
            'can_access_sppra' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'receives_submissions' => $request->boolean('receives_submissions'),
            'can_access_sppra' => $request->boolean('can_access_sppra'),
        ];
    }

    private function validatedForInvite(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'department_id' => ['nullable', Rule::requiredIf($request->input('role') === User::ROLE_DEPARTMENT_USER), 'exists:departments,id'],
            'receives_submissions' => ['nullable', 'boolean'],
            'can_access_sppra' => ['nullable', 'boolean'],
        ]);
    }

    private function nameFromEmail(string $email): string
    {
        return Str::of($email)
            ->before('@')
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }
}
