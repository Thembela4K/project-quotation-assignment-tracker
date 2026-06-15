<div class="grid gap-4 md:grid-cols-2">
    <label><span class="label">Name</span><input class="input" name="name" value="{{ old('name', $user->name) }}" @required($user->exists) placeholder="{{ $user->exists ? '' : 'Optional - user can confirm on invitation' }}"></label>
    <label><span class="label">Username</span><input class="input" name="username" value="{{ old('username', $user->username) }}" placeholder="name.surname"></label>
    <label><span class="label">Email</span><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="name@example.com" @required(! $user->exists)></label>
    @if($user->exists)
        <label><span class="label">Password</span><input class="input" type="password" name="password" placeholder="Leave blank to keep current password"></label>
    @else
        <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            The user will receive an invitation email and set their own password.
        </div>
    @endif
    <label>
        <span class="label">Role</span>
        <select class="input" name="role" required>
            @foreach($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">No department</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $user->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid gap-3 md:grid-cols-3">
        @if($user->exists)
            <label class="mt-7 flex items-center gap-2 text-sm text-zinc-700">
                <input class="rounded border-zinc-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                Active
            </label>
        @endif
        <label class="mt-7 flex items-center gap-2 text-sm text-zinc-700">
            <input class="rounded border-zinc-300" type="checkbox" name="receives_submissions" value="1" @checked(old('receives_submissions', $user->receives_submissions))>
            Reviewer
        </label>
        <label class="mt-7 flex items-center gap-2 text-sm text-zinc-700">
            <input class="rounded border-zinc-300" type="checkbox" name="can_access_sppra" value="1" @checked(old('can_access_sppra', $user->can_access_sppra))>
            SPPRA
        </label>
    </div>
</div>
