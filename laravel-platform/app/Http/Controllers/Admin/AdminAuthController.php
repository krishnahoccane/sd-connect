<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AuditService $auditService)
    {
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user && $user->is_super_admin) {
                return redirect()->route('super-admin.auth-settings');
            }

            return redirect('/');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $user = User::query()->where('primary_email', $email)->first();

        if (!$user || empty($user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        if (!$user->isGloballyActive()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is suspended or locked.']);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            $maxAttempts = (int) config('twsd_connect.password.max_login_attempts', 5);
            $lockoutMinutes = (int) config('twsd_connect.password.lockout_minutes', 15);
            $failedCount = $user->failed_login_count + 1;
            $updates = ['failed_login_count' => $failedCount];
            if ($failedCount >= $maxAttempts) {
                $updates['locked_until'] = now()->addMinutes($lockoutMinutes);
            }
            $user->update($updates);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $user->update([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->auditService->record(
            action: 'admin.login_succeeded',
            actor: $user,
            targetType: 'user',
            targetUuid: $user->uuid,
            request: $request,
            actorRole: $user->is_super_admin ? 'super_admin' : 'user'
        );

        return redirect()->intended(
            $user->is_super_admin ? route('super-admin.auth-settings') : '/'
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
