<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f7f2] text-neutral-950">
    <main class="relative grid min-h-screen overflow-hidden px-4 py-8">
        <div class="absolute inset-x-0 top-0 h-3 bg-[#f7de05]"></div>
        <div class="absolute bottom-0 left-0 hidden h-40 w-2/5 bg-[#74bd45] lg:block"></div>
        <div class="absolute bottom-0 right-0 hidden h-40 w-3/5 bg-neutral-950 lg:block"></div>

        <section class="relative mx-auto grid w-full max-w-5xl self-center overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xl shadow-neutral-950/10 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="bg-neutral-950 p-6 text-white sm:p-8">
                <div class="flex items-center gap-3">
                    @include('admin.partials.ihub-logo', ['class' => 'h-12 w-auto shrink-0'])
                    <div>
                        <p class="text-sm font-semibold text-[#f7de05]">iHUB Admin</p>
                        <p class="text-xs text-neutral-300">Picktime workspace</p>
                    </div>
                </div>

                <div class="mt-16 max-w-sm">
                    <p class="text-sm font-medium text-[#74bd45]">Meeting rooms</p>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight">Admin login</h1>
                    <p class="mt-4 text-sm leading-6 text-neutral-300">Manage iHUB reservations, rooms, and daily booking flow from one focused workspace.</p>
                </div>
            </div>

            <form method="POST" action="/admin/login" class="p-6 sm:p-8 lg:p-10">
                @csrf
                <div class="mb-8">
                    <p class="text-sm font-semibold text-[#74bd45]">Secure access</p>
                    <h2 class="mt-2 text-2xl font-semibold">Welcome back</h2>
                </div>

                <div class="grid gap-5">
                    <label class="grid gap-2 text-sm font-medium">Email
                        <input class="h-12 rounded-md border border-neutral-300 bg-white px-4 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="email" name="email" value="{{ old('email') }}" required>
                    </label>
                    <label class="grid gap-2 text-sm font-medium">Password
                        <input class="h-12 rounded-md border border-neutral-300 bg-white px-4 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="password" name="password" required>
                    </label>
                </div>

                <div class="mt-4 flex justify-end">
                    <a class="text-sm font-semibold text-neutral-700 underline decoration-[#74bd45] underline-offset-4 transition hover:text-neutral-950" href="{{ route('password.request') }}">Forgot password?</a>
                </div>

                @if (session('status'))
                    <p class="mt-5 rounded-md border border-[#74bd45]/30 bg-[#74bd45]/10 px-4 py-3 text-sm text-neutral-800">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</p>
                @endif

                <button class="mt-8 h-12 w-full rounded-md bg-neutral-950 px-4 font-semibold text-white transition hover:bg-[#74bd45] hover:text-neutral-950 focus:outline-none focus:ring-2 focus:ring-[#f7de05] focus:ring-offset-2" type="submit">Login</button>

                <div class="my-6 flex items-center gap-3">
                    <div class="h-px flex-1 bg-neutral-200"></div>
                    <span class="text-xs font-semibold uppercase text-neutral-400">or</span>
                    <div class="h-px flex-1 bg-neutral-200"></div>
                </div>

                <a class="flex h-12 w-full items-center justify-center gap-3 rounded-md border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-900 transition hover:border-neutral-950 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-[#f7de05] focus:ring-offset-2" href="{{ route('admin.google.redirect') }}">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" role="img" aria-label="Google logo" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#4285F4" d="M22.6 12.2c0-.8-.1-1.6-.2-2.3H12v4.4h5.9c-.3 1.4-1 2.5-2.1 3.2v2.7h3.4c2-1.8 3.4-4.5 3.4-8z" />
                        <path fill="#34A853" d="M12 23c3 0 5.5-1 7.3-2.7l-3.4-2.7c-1 .6-2.2 1-3.9 1-3 0-5.5-2-6.4-4.7H2.1v2.8C3.9 20.4 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.6 13.9c-.2-.6-.4-1.2-.4-1.9s.1-1.3.4-1.9V7.3H2.1C1.4 8.7 1 10.3 1 12s.4 3.3 1.1 4.7l3.5-2.8z" />
                        <path fill="#EA4335" d="M12 5.4c1.6 0 3.1.6 4.2 1.6l3.1-3.1C17.5 2.1 15 1 12 1 7.7 1 3.9 3.6 2.1 7.3l3.5 2.8C6.5 7.4 9 5.4 12 5.4z" />
                    </svg>
                    Continue with Google
                </a>
            </form>
        </section>
    </main>
</body>
</html>
