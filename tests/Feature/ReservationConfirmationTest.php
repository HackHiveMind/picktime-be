<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Jobs\SendReservationConfirmation;
use App\Mail\ReservationConfirmation;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\PublicBookingService;
use App\Services\ReservationCancellationLinks;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationConfirmationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 11, 1)->startOfDay());
        Mail::fake();
        config(['queue.default' => 'sync']);
    }

    public function test_public_booking_sends_confirmation_with_a_valid_cancellation_link(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $identifier = $this->postJson('/api/reservations', $this->payload())->assertCreated()->json('data.id');
        $reservation = Reservation::findOrFail($identifier);
        $url = app(ReservationCancellationLinks::class)->url($reservation);

        Mail::assertSent(ReservationConfirmation::class, function ($mail) use ($url) {
            $mail->assertSeeInHtml('Rezervarea ta este confirmată');
            $mail->assertSeeInHtml('04.11.2030');
            $mail->assertSeeInHtml('Europe/Chisinau');
            $mail->assertSeeInHtml('Anulează rezervarea');

            return $mail->hasTo('ana@example.com') && $mail->cancellationUrl === $url;
        });
        $this->get($url)->assertOk()->assertSee('Confirmă anularea rezervării');
        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        $this->post($url)->assertOk()->assertSee('Rezervarea a fost anulată');
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->post($url)->assertOk();
        $this->getJson('/api/rooms/imeet/availability?date=2030-11-04')->assertJsonPath('data.slots.2.available', true);
    }

    public function test_unsigned_tampered_and_expired_links_cannot_cancel_a_reservation(): void
    {
        $reservation = $this->reservation();
        $links = app(ReservationCancellationLinks::class);
        $url = $links->url($reservation);
        $this->get('/reservations/'.$reservation->id.'/cancel')->assertForbidden();
        $this->post($url.'&unexpected=value')->assertForbidden();
        $other = $this->reservation(['starts_at' => '12:00', 'ends_at' => '13:00']);
        $this->post(str_replace('/'.$reservation->id.'/cancel', '/'.$other->id.'/cancel', $url))->assertForbidden();
        $this->travelTo($links->startsAt($reservation)->addMinute());
        $this->get($url)->assertForbidden();
        $this->post($url)->assertForbidden();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $other->fresh()->status);
    }

    public function test_changing_recipient_or_time_invalidates_old_links(): void
    {
        $reservation = $this->reservation();
        $links = app(ReservationCancellationLinks::class);
        $url = $links->url($reservation);
        $reservation->update(['email' => 'replacement@example.com']);
        $this->get($url)->assertForbidden();
        $this->post($url)->assertForbidden();
        $replacement = $links->url($reservation);
        $reservation->update(['starts_at' => '11:00', 'ends_at' => '12:00']);
        $this->post($replacement)->assertForbidden();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
    }

    public function test_mail_is_sent_only_after_commit_and_not_for_rolled_back_bookings(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        DB::beginTransaction();
        app(PublicBookingService::class)->create($this->payload(), '11:00');
        Mail::assertNothingSent();
        DB::rollBack();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('reservations', 0);

        DB::transaction(function (): void {
            app(PublicBookingService::class)->create($this->payload(), '11:00');
            Mail::assertNothingSent();
        });
        Mail::assertSentCount(1);
    }

    public function test_stale_or_cancelled_queued_confirmations_are_skipped(): void
    {
        $reservation = $this->reservation();
        $links = app(ReservationCancellationLinks::class);
        $job = new SendReservationConfirmation($reservation->id, $links->version($reservation));
        $reservation->update(['email' => 'replacement@example.com']);
        $job->handle($links);
        $job = new SendReservationConfirmation($reservation->id, $links->version($reservation));
        $reservation->update(['status' => ReservationStatus::Cancelled]);
        $job->handle($links);
        Mail::assertNothingSent();
    }

    public function test_admin_confirmation_and_rescheduling_send_fresh_links_without_duplicate_unchanged_updates(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        $identifier = $this->postJson('/api/admin/reservations', [...$this->payload(), 'status' => 'pending'])->assertCreated()->json('data.id');
        Mail::assertNothingSent();
        $this->putJson('/api/admin/reservations/'.$identifier, [...$this->payload(), 'status' => 'confirmed'])->assertOk();
        Mail::assertSentCount(1);
        $this->putJson('/api/admin/reservations/'.$identifier, [...$this->payload(), 'status' => 'confirmed'])->assertOk();
        Mail::assertSentCount(1);
        $this->putJson('/api/admin/reservations/'.$identifier, [...$this->payload(), 'date' => '2030-11-05', 'status' => 'confirmed'])->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_confirmed_admin_and_web_bookings_also_send_confirmation(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/admin/reservations', [...$this->payload(), 'status' => 'confirmed'])->assertCreated();
        $this->post('/admin/reservations', [...$this->payload(), 'date' => '2030-11-05', 'status' => 'confirmed'])->assertRedirect('/admin');
        Mail::assertSentCount(2);
        $this->postJson('/api/reservations', $this->payload())->assertUnprocessable();
        Mail::assertSentCount(2);
    }

    private function payload(): array
    {
        return ['locale' => 'ro', 'room_id' => 'imeet', 'date' => '2030-11-04', 'start_time' => '10:00', 'first_name' => 'Ana', 'last_name' => 'Popescu', 'email' => 'ana@example.com', 'phone' => '060000000'];
    }

    public function test_email_and_cancellation_page_keep_the_saved_customer_language(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        foreach (['en' => ['Your booking is confirmed', 'Confirm cancellation', 'Your booking has been cancelled'],
            'ru' => ['Ваше бронирование подтверждено', 'Подтвердить отмену', 'Бронирование отменено']] as $locale => $texts) {
            $identifier = $this->postJson('/api/reservations', [...$this->payload(), 'locale' => $locale, 'date' => $locale === 'en' ? '2030-11-04' : '2030-11-05'])
                ->assertCreated()->json('data.id');
            $reservation = Reservation::findOrFail($identifier);
            $this->assertSame($locale, $reservation->locale);
            app()->setLocale('ro');
            Mail::assertSent(ReservationConfirmation::class, function ($mail) use ($identifier, $texts, $locale) {
                if ($mail->reservation->id != $identifier) {
                    return false;
                }
                $mail->assertSeeInHtml($texts[0]);
                $mail->assertHasSubject(__('bookings.subject', [], $locale));

                return $mail->locale === $locale;
            });
            $url = app(ReservationCancellationLinks::class)->url($reservation);
            $this->withHeader('Accept-Language', 'ro')->get($url)->assertOk()->assertSee($texts[1]);
            $this->post($url)->assertOk()->assertSee($texts[2]);
        }
    }

    public function test_language_header_is_used_when_locale_is_missing_and_invalid_locales_are_rejected(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $payload = $this->payload();
        unset($payload['locale']);
        $identifier = $this->withHeader('Accept-Language', 'ru-RU, en;q=0.8')->postJson('/api/reservations', $payload)
            ->assertCreated()->json('data.id');
        $this->assertSame('ru', Reservation::findOrFail($identifier)->locale);
        $this->postJson('/api/reservations', [...$payload, 'date' => '2030-11-05', 'locale' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('locale');
    }

    public function test_admin_edit_preserves_customer_language_when_not_supplied(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $identifier = $this->postJson('/api/reservations', [...$this->payload(), 'locale' => 'en'])->assertCreated()->json('data.id');
        $payload = [...$this->payload(), 'date' => '2030-11-05'];
        unset($payload['locale']);
        $this->actingAs(User::factory()->create())->putJson('/api/admin/reservations/'.$identifier, $payload)->assertOk();
        $this->assertSame('en', Reservation::findOrFail($identifier)->locale);
        Mail::assertSent(ReservationConfirmation::class, fn ($mail) => $mail->reservation->reserved_date->format('Y-m-d') === '2030-11-05' && $mail->locale === 'en');
    }

    private function reservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reserved_date' => '2030-11-04',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            ...$attributes,
        ]);
    }
}
