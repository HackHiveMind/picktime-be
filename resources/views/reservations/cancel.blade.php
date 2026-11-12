<!doctype html>
<html lang="{{ $reservation->locale ?? 'ro' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('bookings.cancel_title', [], $reservation->locale ?? 'ro') }}</title>
</head>
<body style="font-family:Arial,sans-serif;max-width:640px;margin:48px auto;padding:20px;line-height:1.6">
    @php($language = $reservation->locale ?? 'ro')
    @if ($reservation->status === \App\Enums\ReservationStatus::Cancelled)
        <h1>{{ __('bookings.cancelled', [], $language) }}</h1>
        <p>{{ __('bookings.released', [], $language) }}</p>
    @else
        <h1>{{ __('bookings.question', [], $language) }}</h1>
        <p>{{ $reservation->room->name }} · {{ $reservation->reserved_date->format('d.m.Y') }}</p>
        <p>{{ substr($reservation->starts_at, 0, 5) }}–{{ substr($reservation->ends_at, 0, 5) }} · {{ __('bookings.timezone', [], $language) }}</p>
        <p>{{ __('bookings.consequence', [], $language) }}</p>
        <form method="POST" action="{{ $action }}">
            @csrf
            <button type="submit" style="padding:12px 20px;cursor:pointer">{{ __('bookings.confirm_cancel', [], $language) }}</button>
        </form>
        <p>{{ __('bookings.keep', [], $language) }}</p>
    @endif
</body>
</html>
