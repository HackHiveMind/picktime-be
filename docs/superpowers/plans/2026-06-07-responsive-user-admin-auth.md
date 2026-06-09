# Responsive User Admin Auth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a responsive public booking interface and protect admin pages/API with one-time first-admin registration plus login/logout.

**Architecture:** Use Laravel session authentication for admin access and keep public booking APIs open. Use Blade + Tailwind for user, auth, and admin pages, with small vanilla JavaScript modules for API-backed interactions. Protect `/api/admin/*` with `auth` while preserving JSON 401 behavior for API clients.

**Tech Stack:** Laravel 13, PHP 8.5, Blade, Tailwind CSS v4, Vite, vanilla JavaScript, PHPUnit feature tests.

---

## File Structure

- Create `app/Http/Controllers/AdminAuthController.php`: renders login/register forms, handles first-admin registration, login, logout.
- Create `app/Http/Controllers/AdminDashboardController.php`: renders the authenticated admin reservations page.
- Modify `routes/web.php`: add `/`, `/admin/register`, `/admin/login`, `/admin/logout`, `/admin` routes.
- Modify `routes/api.php`: wrap `/api/admin/reservations*` routes in `auth` middleware.
- Create `resources/views/booking.blade.php`: responsive public booking interface.
- Create `resources/views/admin/login.blade.php`: admin login form.
- Create `resources/views/admin/register.blade.php`: first-admin registration form.
- Create `resources/views/admin/dashboard.blade.php`: responsive admin reservation management page.
- Modify `resources/js/app.js`: add public booking and admin dashboard vanilla JS behavior.
- Create `tests/Feature/AdminAuthTest.php`: auth behavior tests.
- Modify `tests/Feature/AdminReservationApiTest.php`: authenticate admin API tests.
- Modify `tests/Feature/BookingApiTest.php`: assert public booking endpoints remain unauthenticated.

---

### Task 1: Add Admin Auth Tests

**Files:**
- Create: `tests/Feature/AdminAuthTest.php`

- [ ] **Step 1: Write failing auth tests**

Create `tests/Feature/AdminAuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
```

- [ ] **Step 2: Run auth tests and verify red**

Run:

```bash
php artisan test tests/Feature/AdminAuthTest.php
```

Expected: FAIL because `/admin/register`, `/admin/login`, `/admin/logout`, and `/admin` routes do not exist yet.

---

### Task 2: Implement Admin Auth Routes and Controller

**Files:**
- Create: `app/Http/Controllers/AdminAuthController.php`
- Create: `app/Http/Controllers/AdminDashboardController.php`
- Modify: `routes/web.php`
- Create: `resources/views/admin/login.blade.php`
- Create: `resources/views/admin/register.blade.php`
- Create: `resources/views/admin/dashboard.blade.php`

- [ ] **Step 1: Add admin auth controller**

Create `app/Http/Controllers/AdminAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        return redirect('/admin');
    }

    public function showLogin(): View
    {
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

        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
```

- [ ] **Step 2: Add admin dashboard controller**

Create `app/Http/Controllers/AdminDashboardController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
```

- [ ] **Step 3: Add web routes**

Replace `routes/web.php` with:

```php
<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'booking');

Route::get('/admin/register', [AdminAuthController::class, 'showRegister'])->name('admin.register');
Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth');

Route::get('/admin', AdminDashboardController::class)->middleware('auth')->name('admin.dashboard');
```

- [ ] **Step 4: Add minimal auth/admin views**

Create `resources/views/admin/register.blade.php`:

```blade
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-950">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-8">
        <form method="POST" action="/admin/register" class="w-full rounded-lg bg-white p-6 shadow-sm">
            @csrf
            <h1 class="text-xl font-semibold">Create first admin</h1>
            <div class="mt-6 grid gap-4">
                <label class="grid gap-1 text-sm">Name <input class="rounded-md border border-neutral-300 px-3 py-2" name="name" value="{{ old('name') }}" required></label>
                <label class="grid gap-1 text-sm">Email <input class="rounded-md border border-neutral-300 px-3 py-2" type="email" name="email" value="{{ old('email') }}" required></label>
                <label class="grid gap-1 text-sm">Password <input class="rounded-md border border-neutral-300 px-3 py-2" type="password" name="password" required></label>
                <label class="grid gap-1 text-sm">Confirm password <input class="rounded-md border border-neutral-300 px-3 py-2" type="password" name="password_confirmation" required></label>
            </div>
            @if ($errors->any())
                <p class="mt-4 text-sm text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button class="mt-6 w-full rounded-md bg-neutral-950 px-4 py-2 text-white" type="submit">Register</button>
        </form>
    </main>
</body>
</html>
```

Create `resources/views/admin/login.blade.php`:

```blade
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-950">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-8">
        <form method="POST" action="/admin/login" class="w-full rounded-lg bg-white p-6 shadow-sm">
            @csrf
            <h1 class="text-xl font-semibold">Admin login</h1>
            <div class="mt-6 grid gap-4">
                <label class="grid gap-1 text-sm">Email <input class="rounded-md border border-neutral-300 px-3 py-2" type="email" name="email" value="{{ old('email') }}" required></label>
                <label class="grid gap-1 text-sm">Password <input class="rounded-md border border-neutral-300 px-3 py-2" type="password" name="password" required></label>
            </div>
            @if ($errors->any())
                <p class="mt-4 text-sm text-rose-700">{{ $errors->first() }}</p>
            @endif
            <button class="mt-6 w-full rounded-md bg-neutral-950 px-4 py-2 text-white" type="submit">Login</button>
        </form>
    </main>
</body>
</html>
```

Create `resources/views/admin/dashboard.blade.php`:

```blade
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Reservations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-950">
    <main class="mx-auto w-full max-w-7xl px-4 py-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold">Reservations</h1>
            <form method="POST" action="/admin/logout">@csrf <button class="rounded-md border border-neutral-300 px-3 py-2 text-sm" type="submit">Logout</button></form>
        </header>
        <section data-admin-app class="mt-6 rounded-lg bg-white p-4 shadow-sm">
            <p class="text-sm text-neutral-600">Loading reservations...</p>
        </section>
    </main>
</body>
</html>
```

- [ ] **Step 5: Run auth tests and verify green**

Run:

```bash
php artisan test tests/Feature/AdminAuthTest.php
```

Expected: PASS.

---

### Task 3: Protect Admin API Routes

**Files:**
- Modify: `routes/api.php`
- Modify: `tests/Feature/AdminReservationApiTest.php`
- Modify: `tests/Feature/BookingApiTest.php`

- [ ] **Step 1: Add failing API auth assertions**

In `tests/Feature/AdminReservationApiTest.php`, add `use App\Models\User;` and update each admin API test to authenticate:

```php
$this->actingAs(User::factory()->create());
```

Add this test:

```php
public function test_admin_reservations_require_authentication(): void
{
    $this->getJson('/api/admin/reservations')
        ->assertUnauthorized();
}
```

In `tests/Feature/BookingApiTest.php`, add:

```php
public function test_public_booking_endpoints_remain_open_without_login(): void
{
    $room = Room::factory()->create(['slug' => 'imeet']);

    $this->getJson('/api/rooms')->assertOk();
    $this->getJson("/api/rooms/{$room->slug}/availability?date=2026-07-04")->assertOk();
}
```

- [ ] **Step 2: Run API tests and verify red**

Run:

```bash
php artisan test tests/Feature/AdminReservationApiTest.php tests/Feature/BookingApiTest.php
```

Expected: FAIL because unauthenticated admin API still returns 200.

- [ ] **Step 3: Wrap admin API routes with auth middleware**

In `routes/api.php`, wrap admin routes:

```php
Route::middleware('auth')->group(function (): void {
    Route::get('/admin/reservations', [AdminReservationController::class, 'index']);
    Route::post('/admin/reservations', [AdminReservationController::class, 'store']);
    Route::put('/admin/reservations/{reservation}', [AdminReservationController::class, 'update']);
    Route::patch('/admin/reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel']);
    Route::delete('/admin/reservations/{reservation}', [AdminReservationController::class, 'destroy']);
});
```

- [ ] **Step 4: Verify API tests pass**

Run:

```bash
php artisan test tests/Feature/AdminReservationApiTest.php tests/Feature/BookingApiTest.php
```

Expected: PASS.

---

### Task 4: Build Responsive Public Booking Page

**Files:**
- Create: `resources/views/booking.blade.php`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Add responsive booking markup**

Create `resources/views/booking.blade.php`:

```blade
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>iHUB Booking</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-100 text-neutral-950">
    <main data-booking-app class="mx-auto grid min-h-screen w-full max-w-6xl gap-6 px-4 py-6 md:grid-cols-[0.9fr_1.1fr] md:px-6 lg:py-10">
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-amber-700">iHUB Rooms</p>
            <h1 class="mt-2 text-2xl font-semibold">Book a meeting room</h1>
            <div class="mt-6 grid gap-4">
                <label class="grid gap-1 text-sm">Room <select data-room-select class="rounded-md border border-neutral-300 px-3 py-2"></select></label>
                <label class="grid gap-1 text-sm">Date <input data-date-input class="rounded-md border border-neutral-300 px-3 py-2" type="date" required></label>
            </div>
            <div data-slot-list class="mt-6 grid gap-2"></div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Your details</h2>
            <form data-booking-form class="mt-6 grid gap-4">
                <input type="hidden" name="room_id">
                <input type="hidden" name="date">
                <input type="hidden" name="start_time">
                <input type="hidden" name="end_time">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm">First name <input class="rounded-md border border-neutral-300 px-3 py-2" name="first_name" required></label>
                    <label class="grid gap-1 text-sm">Last name <input class="rounded-md border border-neutral-300 px-3 py-2" name="last_name" required></label>
                </div>
                <label class="grid gap-1 text-sm">Email <input class="rounded-md border border-neutral-300 px-3 py-2" type="email" name="email" required></label>
                <label class="grid gap-1 text-sm">Phone <input class="rounded-md border border-neutral-300 px-3 py-2" name="phone" required></label>
                <label class="grid gap-1 text-sm">Notes <textarea class="min-h-24 rounded-md border border-neutral-300 px-3 py-2" name="notes"></textarea></label>
                <p data-booking-message class="hidden rounded-md px-3 py-2 text-sm"></p>
                <button class="rounded-md bg-neutral-950 px-4 py-3 text-white disabled:cursor-not-allowed disabled:bg-neutral-400" type="submit" disabled>Book selected time</button>
            </form>
        </section>
    </main>
</body>
</html>
```

- [ ] **Step 2: Add booking JavaScript**

In `resources/js/app.js`, add:

```js
const bookingApp = document.querySelector('[data-booking-app]');

if (bookingApp) {
    const roomSelect = bookingApp.querySelector('[data-room-select]');
    const dateInput = bookingApp.querySelector('[data-date-input]');
    const slotList = bookingApp.querySelector('[data-slot-list]');
    const form = bookingApp.querySelector('[data-booking-form]');
    const message = bookingApp.querySelector('[data-booking-message]');
    const submitButton = form.querySelector('button[type="submit"]');

    const today = new Date().toISOString().slice(0, 10);
    dateInput.value = today;
    form.date.value = today;

    const showMessage = (text, type = 'info') => {
        message.textContent = text;
        message.className = `rounded-md px-3 py-2 text-sm ${type === 'error' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-900'}`;
    };

    const loadRooms = async () => {
        const response = await fetch('/api/rooms', { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        roomSelect.innerHTML = payload.data.map((room) => `<option value="${room.id}">${room.name}</option>`).join('');
        form.room_id.value = roomSelect.value;
        await loadAvailability();
    };

    const selectSlot = (slot) => {
        form.start_time.value = slot.start_time;
        form.end_time.value = slot.end_time;
        submitButton.disabled = false;
        slotList.querySelectorAll('button').forEach((button) => button.dataset.selected = 'false');
        slotList.querySelector(`[data-start="${slot.start_time}"]`).dataset.selected = 'true';
    };

    const loadAvailability = async () => {
        if (!roomSelect.value || !dateInput.value) return;

        form.room_id.value = roomSelect.value;
        form.date.value = dateInput.value;
        submitButton.disabled = true;
        slotList.innerHTML = '<p class="text-sm text-neutral-600">Loading available times...</p>';

        const response = await fetch(`/api/rooms/${roomSelect.value}/availability?date=${dateInput.value}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        const slots = payload.data?.slots ?? payload.data ?? [];
        if (slots.length === 0) {
            slotList.innerHTML = '<p class="text-sm text-neutral-600">No available times for this date.</p>';
            return;
        }

        slotList.innerHTML = slots.map((slot) => `
            <button type="button" data-start="${slot.start_time}" class="rounded-md border border-neutral-300 px-3 py-2 text-left text-sm data-[selected=true]:border-neutral-950 data-[selected=true]:bg-neutral-950 data-[selected=true]:text-white">
                ${slot.start_time} - ${slot.end_time}
            </button>
        `).join('');

        slotList.querySelectorAll('button').forEach((button, index) => {
            button.addEventListener('click', () => selectSlot(slots[index]));
        });
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitButton.disabled = true;

        const response = await fetch('/api/reservations', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.fromEntries(new FormData(form))),
        });

        if (!response.ok) {
            const payload = await response.json();
            showMessage(payload.message || 'Reservation could not be created.', 'error');
            submitButton.disabled = false;
            return;
        }

        form.reset();
        dateInput.value = today;
        showMessage('Reservation created successfully.');
        await loadAvailability();
    });

    roomSelect.addEventListener('change', loadAvailability);
    dateInput.addEventListener('change', loadAvailability);

    loadRooms().catch(() => showMessage('Rooms could not be loaded.', 'error'));
}
```

- [ ] **Step 3: Build frontend**

Run:

```bash
npm run build
```

Expected: build succeeds.

---

### Task 5: Build Responsive Admin Dashboard JavaScript

**Files:**
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Replace dashboard placeholder with containers**

In `resources/views/admin/dashboard.blade.php`, use:

```blade
<section data-admin-app class="mt-6 grid gap-4">
    <div class="rounded-lg bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
            <label class="grid gap-1 text-sm">From <input data-admin-from class="rounded-md border border-neutral-300 px-3 py-2" type="date"></label>
            <button data-admin-refresh class="rounded-md bg-neutral-950 px-4 py-2 text-sm text-white" type="button">Refresh</button>
        </div>
    </div>
    <div data-admin-list class="grid gap-3"></div>
</section>
```

- [ ] **Step 2: Add admin JavaScript**

Append to `resources/js/app.js`:

```js
const adminApp = document.querySelector('[data-admin-app]');

if (adminApp) {
    const fromInput = adminApp.querySelector('[data-admin-from]');
    const refreshButton = adminApp.querySelector('[data-admin-refresh]');
    const list = adminApp.querySelector('[data-admin-list]');

    fromInput.value = new Date().toISOString().slice(0, 10);

    const loadReservations = async () => {
        list.innerHTML = '<p class="rounded-lg bg-white p-4 text-sm text-neutral-600 shadow-sm">Loading reservations...</p>';
        const response = await fetch(`/api/admin/reservations?date_from=${fromInput.value}`, {
            headers: { Accept: 'application/json' },
        });

        if (response.status === 401) {
            window.location.href = '/admin/login';
            return;
        }

        const payload = await response.json();
        const reservations = payload.data ?? [];

        if (reservations.length === 0) {
            list.innerHTML = '<p class="rounded-lg bg-white p-4 text-sm text-neutral-600 shadow-sm">No reservations found.</p>';
            return;
        }

        list.innerHTML = reservations.map((reservation) => `
            <article class="rounded-lg bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">${reservation.first_name} ${reservation.last_name}</h2>
                        <p class="text-sm text-neutral-600">${reservation.room_name} · ${reservation.date} · ${reservation.start_time}-${reservation.end_time}</p>
                        <p class="text-sm text-neutral-600">${reservation.email} · ${reservation.phone}</p>
                    </div>
                    <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium">${reservation.status}</span>
                </div>
            </article>
        `).join('');
    };

    refreshButton.addEventListener('click', loadReservations);
    loadReservations();
}
```

- [ ] **Step 3: Verify build**

Run:

```bash
npm run build
```

Expected: build succeeds.

---

### Task 6: Browser Verification and Final Test Pass

**Files:**
- No planned code edits unless verification reveals a defect.

- [ ] **Step 1: Run full backend test suite**

Run:

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 2: Run production frontend build**

Run:

```bash
npm run build
```

Expected: build succeeds.

- [ ] **Step 3: Start local server**

Run:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Expected: server listens on `http://127.0.0.1:8000`.

- [ ] **Step 4: Verify browser pages**

Open and inspect:

```text
http://127.0.0.1:8000/
http://127.0.0.1:8000/admin/register
http://127.0.0.1:8000/admin/login
http://127.0.0.1:8000/admin
```

Expected:

- Public booking page fits at mobile width around 390px and desktop width around 1440px.
- Register page appears when there are no users.
- Login page appears after a user exists.
- Admin page redirects to login when unauthenticated.
- Authenticated admin page loads reservations without 405 or 401.

---

## Self-Review

- Spec coverage: public responsive booking UI is covered in Task 4; admin one-time register/login/logout is covered in Tasks 1-2; admin API protection is covered in Task 3; admin UI is covered in Task 5; verification is covered in Task 6.
- Placeholder scan: no TBD/TODO/implement-later placeholders are present.
- Type consistency: route paths, controller names, view names, and data attributes are consistent across tasks.
