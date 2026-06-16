<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AdminAuthController extends Controller
{
    public function showRegister(): RedirectResponse|View
    {
        if (User::query()->exists()) {
            return redirect('/admin/login');
        }

        return view('admin.register');
    }

    public function register(Request $request): RedirectResponse
    {
        if (User::query()->exists()) {
            return redirect('/admin/login');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create($validated);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectToAdminDashboard();
    }

    public function showLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            return $this->redirectToAdminDashboard();
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return $this->redirectToAdminDashboard();
    }

    public function showForgotPassword(): View
    {
        return view('admin.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('admin.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/admin/login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        $this->ensureGoogleLoginIsConfigured();

        return Socialite::driver('google')
            ->with(['prompt' => 'login'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $this->ensureGoogleLoginIsConfigured();

        $googleUser = Socialite::driver('google')->user();

        $user = User::query()
            ->where('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'This Google account is not allowed for admin access.',
            ])->redirectTo('/admin/login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectToAdminDashboard();
    }

    private function redirectToAdminDashboard(): RedirectResponse
    {
        $dashboardUrl = config('app.admin_dashboard_url', '/admin');

        if (! Auth::check() || ! str_starts_with($dashboardUrl, 'http')) {
            return redirect($dashboardUrl);
        }

        return redirect($this->appendAdminToken($dashboardUrl, Auth::user()));
    }

    private function ensureGoogleLoginIsConfigured(): void
    {
        if (filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Google login is not configured yet.',
        ])->redirectTo('/admin/login');
    }

    private function appendAdminToken(string $dashboardUrl, User $user): string
    {
        $token = Str::random(64);

        $user->forceFill([
            'admin_api_token_hash' => hash('sha256', $token),
            'admin_api_token_expires_at' => now()->addHours(12),
        ])->save();

        $separator = str_contains($dashboardUrl, '?') ? '&' : '?';

        return $dashboardUrl.$separator.'admin_token='.urlencode($token);
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $request->user()->forceFill([
                'admin_api_token_hash' => null,
                'admin_api_token_expires_at' => null,
            ])->save();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
