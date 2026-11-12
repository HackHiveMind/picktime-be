<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class BookingSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_revokes_existing_bearer_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'admin_api_token_hash' => hash('sha256', 'existing-test-token'),
            'admin_api_token_expires_at' => now()->addHours(12),
        ])->save();
        $rememberToken = $user->remember_token;

        $this->post('/admin/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertRedirect('/admin/login');

        $user->refresh();
        $this->assertTrue(Hash::check('replacement-password', $user->password));
        $this->assertNull($user->admin_api_token_hash);
        $this->assertNull($user->admin_api_token_expires_at);
        $this->assertNotSame($rememberToken, $user->remember_token);

        Auth::forgetGuards();
        $this->withToken('existing-test-token')->getJson('/api/admin/rooms')->assertUnauthorized();
    }

    public function test_seeding_is_repeatable_and_does_not_create_admin_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('rooms', 4);
    }

    public function test_api_and_web_reject_default_end_time_crossing_midnight(): void
    {
        $room = Room::factory()->create();
        $this->actingAs(User::factory()->create());
        $payload = $this->payload($room->slug, '23:30');

        $this->postJson('/api/admin/reservations', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->from('/admin')->post('/admin/reservations', $payload)
            ->assertRedirect('/admin')->assertSessionHasErrors('end_time');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_web_rebooking_preserves_cancelled_history_and_rejects_overlap(): void
    {
        $room = Room::factory()->create();
        $original = Reservation::factory()->for($room)->create([
            'status' => ReservationStatus::Cancelled,
            'reserved_date' => '2030-11-04',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'email' => 'original@example.com',
        ]);
        $this->actingAs(User::factory()->create());

        $this->post('/admin/reservations', $this->payload($room->slug))
            ->assertRedirect('/admin')->assertSessionHasNoErrors();
        $this->from('/admin')->post('/admin/reservations', $this->payload($room->slug))
            ->assertRedirect('/admin')->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseHas('reservations', [
            'id' => $original->id,
            'status' => 'cancelled',
            'email' => 'original@example.com',
        ]);
    }

    private function payload(string $roomSlug, string $startTime = '10:00'): array
    {
        return [
            'room_id' => $roomSlug,
            'date' => '2030-11-04',
            'start_time' => $startTime,
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '060000000',
            'status' => 'confirmed',
        ];
    }
}
