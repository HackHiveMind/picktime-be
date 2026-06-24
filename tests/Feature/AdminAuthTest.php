<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_admin_can_register_and_is_logged_in(): void
    {
        $this->post('/admin/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password-secret',
            'password_confirmation' => 'password-secret',
        ])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
    }

    public function test_admin_register_is_blocked_after_first_user_exists(): void
    {
        User::factory()->create();

        $this->get('/admin/register')->assertRedirect('/admin/login');

        $this->post('/admin/register', [
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'password' => 'password-secret',
            'password_confirmation' => 'password-secret',
        ])
            ->assertRedirect('/admin/login');

        $this->assertDatabaseMissing('users', [
            'email' => 'second@example.com',
        ]);
    }

    public function test_admin_can_login_and_logout(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password-secret'),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    public function test_admin_login_can_redirect_to_configured_dashboard_url(): void
    {
        config(['app.admin_dashboard_url' => 'https://pictime-ihub-booking-fe.vercel.app/admin']);

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password-secret'),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ])
            ->assertRedirectContains('https://pictime-ihub-booking-fe.vercel.app/admin?admin_token=');
    }

    public function test_admin_login_token_can_access_admin_api_without_session(): void
    {
        config(['app.admin_dashboard_url' => 'https://pictime-ihub-booking-fe.vercel.app/admin']);

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password-secret'),
        ]);

        $redirectUrl = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ])
            ->assertRedirect()
            ->headers
            ->get('Location');

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?: '', $query);

        $this->assertNotEmpty($query['admin_token'] ?? null);

        $this->flushSession();

        $this->withHeader('Authorization', 'Bearer '.$query['admin_token'])
            ->getJson('/api/admin/rooms')
            ->assertOk();
    }

    public function test_performance_admin_token_can_access_admin_api_without_user_lookup(): void
    {
        config(['services.performance_admin_token' => 'performance-secret']);

        Room::factory()->create(['slug' => 'imeet']);

        DB::enableQueryLog();

        $this->withHeader('Authorization', 'Bearer performance-secret')
            ->getJson('/api/admin/rooms')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'imeet');

        $userQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from "users"'))
            ->count();

        $this->assertSame(0, $userQueries);
    }

    public function test_invalid_performance_admin_token_still_requires_authentication(): void
    {
        config(['services.performance_admin_token' => 'performance-secret']);

        $this->withHeader('Authorization', 'Bearer wrong-secret')
            ->getJson('/api/admin/rooms')
            ->assertUnauthorized();
    }

    public function test_admin_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password-secret'),
        ]);

        $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        User::factory()->create();

        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_authenticated_admin_is_redirected_from_login_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }

    public function test_authenticated_admin_is_redirected_from_login_to_configured_dashboard_url(): void
    {
        config(['app.admin_dashboard_url' => 'https://pictime-ihub-booking-fe.vercel.app/admin']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirectContains('https://pictime-ihub-booking-fe.vercel.app/admin?admin_token=');
    }

    public function test_admin_auth_pages_show_ihub_branding(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('aria-label="iHUB logo"', false)
            ->assertSee('iHUB Admin')
            ->assertSee('Picktime workspace');

        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('aria-label="iHUB logo"', false)
            ->assertSee('iHUB Admin')
            ->assertSee('Create first admin');
    }

    public function test_admin_login_page_shows_google_login_option(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('/admin/google', false);
    }

    public function test_admin_login_page_shows_password_reset_link(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('/admin/forgot-password', false);
    }

    public function test_admin_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $this->from('/admin/forgot-password')->post('/admin/forgot-password', [
            'email' => 'admin@example.com',
        ])
            ->assertRedirect('/admin/forgot-password')
            ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_admin_can_reset_password_with_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->post('/admin/forgot-password', [
            'email' => 'admin@example.com',
        ]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('status', __(Password::PASSWORD_RESET));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_admin_google_login_redirects_to_google_provider(): void
    {
        $this->configureGoogleLogin();
        Socialite::fake('google');

        $this->get('/admin/google')
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_admin_google_login_requests_google_reauthentication(): void
    {
        $this->configureGoogleLogin();

        $redirectUrl = $this->get('/admin/google')
            ->assertRedirect()
            ->headers
            ->get('Location');

        $this->assertStringContainsString('prompt=login', $redirectUrl);
    }

    public function test_admin_google_login_requires_oauth_configuration(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get('/admin/google')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');
    }

    public function test_existing_admin_can_login_with_google(): void
    {
        $this->configureGoogleLogin();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-admin-123',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]));

        $this->get('/admin/google/callback')
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_existing_admin_google_login_can_redirect_to_configured_dashboard_url(): void
    {
        config(['app.admin_dashboard_url' => 'https://pictime-ihub-booking-fe.vercel.app/admin']);

        $this->configureGoogleLogin();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-admin-123',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]));

        $this->get('/admin/google/callback')
            ->assertRedirectContains('https://pictime-ihub-booking-fe.vercel.app/admin?admin_token=');

        $this->assertAuthenticated();
    }

    public function test_google_login_rejects_unknown_admin_email(): void
    {
        $this->configureGoogleLogin();

        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-unknown-123',
            'name' => 'Unknown User',
            'email' => 'unknown@example.com',
        ]));

        $this->get('/admin/google/callback')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function configureGoogleLogin(): void
    {
        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
        ]);
    }

    public function test_home_page_redirects_to_admin_login(): void
    {
        $this->get('/')
            ->assertRedirect('/admin/login');
    }

    public function test_home_page_redirects_authenticated_admin_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/admin');
    }

    public function test_logged_in_admin_session_can_access_admin_api(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password-secret'),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password-secret',
        ]);

        $this->getJson('/api/admin/rooms')
            ->assertOk();
    }

    public function test_admin_login_uses_forwarded_https_scheme_for_assets(): void
    {
        $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('https://localhost', false)
            ->assertDontSee('http://localhost/build/assets', false);
    }
}
