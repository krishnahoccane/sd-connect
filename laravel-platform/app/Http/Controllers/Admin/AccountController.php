<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\UserProfile;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function showProfile(Request $request): View
    {
        $user = $request->user()->load('profile');

        return view('admin.profile', [
            'user' => $user,
            'profile' => $user->profile,
            'consoleHomeUrl' => $this->resolveConsoleHome($user),
            'commonTimezones' => [
                'UTC',
                'Asia/Kolkata',
                'Asia/Singapore',
                'Asia/Dubai',
                'Europe/London',
                'America/New_York',
                'America/Los_Angeles',
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rawPhone = trim((string) $request->input('primary_phone', ''));
        $normalizedPhone = $rawPhone !== '' ? preg_replace('/\s+/', '', $rawPhone) : null;
        $request->merge(['primary_phone' => $normalizedPhone]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:160'],
            'given_name' => ['nullable', 'string', 'max:80'],
            'family_name' => ['nullable', 'string', 'max:80'],
            'primary_phone' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^\+[1-9]\d{6,14}$/',
                Rule::unique('users', 'primary_phone')->ignore($user->id),
            ],
            'locale' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ], [
            'primary_phone.regex' => 'Enter phone number in international E.164 format (e.g. +919876543210).',
        ]);

        $phoneChanged = $user->primary_phone !== ($validated['primary_phone'] ?? null);
        $user->update([
            'primary_phone' => $validated['primary_phone'] ?? null,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ]);

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => trim($validated['display_name']),
                'given_name' => isset($validated['given_name']) ? trim((string) $validated['given_name']) ?: null : null,
                'family_name' => isset($validated['family_name']) ? trim((string) $validated['family_name']) ?: null : null,
                'locale' => $validated['locale'] ?? 'en',
                'timezone' => $validated['timezone'] ?? 'UTC',
            ]
        );

        $this->auditService->record(
            action: 'admin.profile_updated',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            afterState: [
                'display_name' => trim($validated['display_name']),
                'primary_phone_set' => !empty($validated['primary_phone']),
            ],
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );

        return back()->with('status', 'Profile updated successfully.');
    }

    public function showPassword(Request $request): View
    {
        $user = $request->user();

        return view('admin.change-password', [
            'user' => $user,
            'hasExistingPassword' => !empty($user->password),
            'policy' => config('twsd_connect.password'),
            'consoleHomeUrl' => $this->resolveConsoleHome($user),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $policy = config('twsd_connect.password');
        $minLength = (int) ($policy['min_length'] ?? 12);

        $rules = [
            'new_password' => ['required', 'string', "min:{$minLength}", 'confirmed'],
        ];
        if (!empty($user->password)) {
            $rules['current_password'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        if (!empty($user->password) && !Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $newPassword = $validated['new_password'];
        if (($policy['require_uppercase'] ?? true) && !preg_match('/[A-Z]/', $newPassword)) {
            return back()->withErrors(['new_password' => 'New password must include at least one uppercase letter.']);
        }
        if (($policy['require_lowercase'] ?? true) && !preg_match('/[a-z]/', $newPassword)) {
            return back()->withErrors(['new_password' => 'New password must include at least one lowercase letter.']);
        }
        if (($policy['require_number'] ?? true) && !preg_match('/[0-9]/', $newPassword)) {
            return back()->withErrors(['new_password' => 'New password must include at least one number.']);
        }
        if (($policy['require_symbol'] ?? true) && !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            return back()->withErrors(['new_password' => 'New password must include at least one symbol.']);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'failed_login_count' => 0,
            'locked_until' => null,
        ]);

        $request->session()->regenerate();

        $this->auditService->record(
            action: 'admin.password_changed',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );

        return back()->with('status', 'Password updated successfully.');
    }

    private function resolveConsoleHome($user): string
    {
        if ($user->is_super_admin) {
            return route('super-admin.auth-settings');
        }

        $firstOrgId = $user->memberships()
            ->where('membership_status', 'active')
            ->value('organisation_id');

        if ($firstOrgId) {
            $org = Organisation::query()->find($firstOrgId);
            if ($org) {
                return route('org.applications', $org->uuid);
            }
        }

        return '/';
    }
}
