@extends('layouts.admin')

@section('title', __('users.title'))

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('users.title') }}</h1>
        @if (session('user_created'))
            <p class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-800" role="status">{{ __('users.created') }}</p>
        @endif
        @if (session('user_deactivated'))
            <p class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-800" role="status">{{ __('users.deactivated') }}</p>
        @endif
        @error('deactivate')<p class="rounded-lg bg-rose-50 p-3 text-rose-800" role="alert">{{ $message }}</p>@enderror

        <div class="grid items-start gap-5 md:grid-cols-2">
            <form action="{{ route('admin.users.store') }}" method="POST" class="min-w-0 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                @csrf
                <h2 class="text-lg font-semibold">{{ __('users.create') }}</h2>
                @foreach (['name' => 'text', 'email' => 'email', 'password' => 'password', 'password_confirmation' => 'password'] as $field => $type)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium">{{ __('users.fields.'.$field) }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" required
                            @if ($type === 'password') autocomplete="new-password" minlength="12" maxlength="72"
                            @else value="{{ is_string(old($field)) ? old($field) : '' }}" maxlength="255" @endif
                            @if ($field === 'password') aria-describedby="password-hint" @endif
                            @error($field) aria-invalid="true" @enderror
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @if ($field === 'password')
                            <p id="password-hint" class="mt-1 text-xs text-slate-600">{{ __('users.hint') }}</p>
                        @endif
                        @error($field)<p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="role" class="block text-sm font-medium">{{ __('users.fields.role') }}</label>
                    <select id="role" name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ __('users.roles.'.$role) }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">{{ __('users.create') }}</button>
            </form>

            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="directory-title">
                <h2 id="directory-title" class="text-lg font-semibold">{{ __('users.directory') }}</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <li class="break-words py-3 text-sm">
                            <p class="font-semibold">{{ $user->name }}</p>
                            <p class="text-slate-600">{{ $user->email }} · {{ __('users.roles.'.$user->role) }}</p>
                            @if ($user->disabled_at !== null)
                                <p class="mt-2 font-medium text-slate-700">{{ __('users.inactive') }}</p>
                            @elseif (in_array($user->role, $roles, true))
                                <form action="{{ route('admin.users.deactivate', $user) }}" method="POST" class="mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-800">{{ __('users.deactivate') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                {{ $users->links() }}
            </section>
        </div>
    </div>
@endsection
