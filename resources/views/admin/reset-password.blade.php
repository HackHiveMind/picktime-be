<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Admin Password</title>
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
                        <p class="text-xs text-neutral-300">Password recovery</p>
                    </div>
                </div>

                <div class="mt-16 max-w-sm">
                    <p class="text-sm font-medium text-[#74bd45]">New password</p>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight">Choose a secure password</h1>
                    <p class="mt-4 text-sm leading-6 text-neutral-300">Use the link from your email to set a new password for the admin workspace.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="p-6 sm:p-8 lg:p-10">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-8">
                    <p class="text-sm font-semibold text-[#74bd45]">Secure reset</p>
                    <h2 class="mt-2 text-2xl font-semibold">Set new password</h2>
                </div>

                <div class="grid gap-5">
                    <label class="grid gap-2 text-sm font-medium">Email
                        <input class="h-12 rounded-md border border-neutral-300 bg-white px-4 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="email" name="email" value="{{ old('email', $email) }}" required autofocus>
                    </label>
                    <label class="grid gap-2 text-sm font-medium">New password
                        <input class="h-12 rounded-md border border-neutral-300 bg-white px-4 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="password" name="password" required>
                    </label>
                    <label class="grid gap-2 text-sm font-medium">Confirm password
                        <input class="h-12 rounded-md border border-neutral-300 bg-white px-4 outline-none transition focus:border-[#74bd45] focus:ring-2 focus:ring-[#74bd45]/20" type="password" name="password_confirmation" required>
                    </label>
                </div>

                @if ($errors->any())
                    <p class="mt-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</p>
                @endif

                <button class="mt-8 h-12 w-full rounded-md bg-neutral-950 px-4 font-semibold text-white transition hover:bg-[#74bd45] hover:text-neutral-950 focus:outline-none focus:ring-2 focus:ring-[#f7de05] focus:ring-offset-2" type="submit">Reset password</button>

                <a class="mt-5 inline-flex text-sm font-semibold text-neutral-700 underline decoration-[#74bd45] underline-offset-4 transition hover:text-neutral-950" href="{{ route('login') }}">Back to login</a>
            </form>
        </section>
    </main>
</body>
</html>
