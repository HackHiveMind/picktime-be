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
            <div class="flex items-center gap-3">
                @include('admin.partials.ihub-logo', ['class' => 'h-10 w-auto shrink-0'])
                <h1 class="text-xl font-semibold">Reservations</h1>
            </div>
            <form method="POST" action="/admin/logout">
                @csrf
                <button class="rounded-md border border-neutral-300 px-3 py-2 text-sm" type="submit">Logout</button>
            </form>
        </header>
        @if (session('status'))
            <p class="mt-6 rounded-md border border-[#74bd45]/30 bg-[#74bd45]/10 px-4 py-3 text-sm text-neutral-800">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <p class="mt-6 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</p>
        @endif

        <section class="mt-6 rounded-lg bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Create reservation</h2>
            </div>

            <form method="POST" action="{{ route('admin.reservations.store') }}" class="grid gap-4 lg:grid-cols-4">
                @csrf
                <label class="grid gap-2 text-sm font-medium">Room
                    <select class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="room_id" required>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->slug }}" @selected(old('room_id') === $room->slug)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium">Date
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">Start
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="time" name="start_time" value="{{ old('start_time', '09:00') }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">End
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="time" name="end_time" value="{{ old('end_time', '10:00') }}">
                </label>
                <label class="grid gap-2 text-sm font-medium">First name
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="first_name" value="{{ old('first_name') }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">Last name
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="last_name" value="{{ old('last_name') }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">Email
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">Phone
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="phone" value="{{ old('phone') }}" required>
                </label>
                <label class="grid gap-2 text-sm font-medium">Status
                    <select class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="status" required>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'confirmed') === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-medium lg:col-span-2">Notes
                    <input class="h-11 rounded-md border border-neutral-300 bg-white px-3 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" name="notes" value="{{ old('notes') }}">
                </label>
                <div class="flex items-end">
                    <button class="h-11 w-full rounded-md bg-neutral-950 px-4 text-sm font-semibold text-white transition hover:bg-[#74bd45] hover:text-neutral-950 focus:outline-none focus:ring-2 focus:ring-[#f7de05] focus:ring-offset-2" type="submit">Create reservation</button>
                </div>
            </form>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg bg-white shadow-sm">
            @if ($reservations->isEmpty())
                <div class="p-4">
                    <p class="text-sm text-neutral-600">No reservations yet.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Guest</th>
                                <th class="px-4 py-3">Room</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            @foreach ($reservations as $reservation)
                                <tr class="align-top">
                                    <td class="px-4 py-3 font-medium text-neutral-950">
                                        {{ $reservation->first_name }} {{ $reservation->last_name }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">
                                        {{ $reservation->room->name }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">
                                        {{ $reservation->reserved_date->format('Y-m-d') }}
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">
                                        {{ substr($reservation->starts_at, 0, 5) }}-{{ substr($reservation->ends_at, 0, 5) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-md bg-[#74bd45]/15 px-2 py-1 text-xs font-semibold capitalize text-neutral-900">
                                            {{ $reservation->status->value }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">
                                        <div>{{ $reservation->email }}</div>
                                        <div class="text-neutral-500">{{ $reservation->phone }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.reservations.destroy', $reservation) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</body>
</html>
