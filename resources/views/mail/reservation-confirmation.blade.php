<!doctype html>
<html lang="{{ $reservation->locale ?? 'ro' }}">
<head><meta charset="utf-8"><title>{{ __('bookings.confirmed', [], $reservation->locale ?? 'ro') }}</title></head>
<body style="font-family:Arial,sans-serif;color:#202020;line-height:1.6">
    @php($language = $reservation->locale ?? 'ro')
    <h1>{{ __('bookings.confirmed', [], $language) }}</h1>
    <p>{{ __('bookings.hello', ['name' => $reservation->first_name], $language) }}</p>
    <p>{{ __('bookings.welcome', [], $language) }}</p>
    <ul>
        <li>{{ __('bookings.room', [], $language) }}: {{ $reservation->room->name }}</li>
        <li>{{ __('bookings.location', [], $language) }}: {{ $reservation->room->location }}</li>
        <li>{{ __('bookings.date', [], $language) }}: {{ $reservation->reserved_date->format('d.m.Y') }}</li>
        <li>{{ __('bookings.time', [], $language) }}: {{ substr($reservation->starts_at, 0, 5) }}–{{ substr($reservation->ends_at, 0, 5) }} ({{ __('bookings.timezone', [], $language) }}, Europe/Chisinau)</li>
        <li>{{ __('bookings.reference', [], $language) }}: {{ $reservation->id }}</li>
    </ul>
    <p>{{ __('bookings.cancel_info', [], $language) }}</p>
    <p><a href="{{ $cancellationUrl }}">{{ __('bookings.cancel', [], $language) }}</a></p>
    <p>{{ __('bookings.link_info', [], $language) }}</p>
</body>
</html>
