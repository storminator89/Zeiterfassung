{{-- Existing login.php markup; credentials and setup policy belong to #76. --}}
<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'">
    <title>{{ $page->copy->text('LOGIN_TITLE') }}</title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    @include('chrono.partials.auth-style')
</head>

<body class="chrono-auth bg-gradient-to-r from-blue-100 to-blue-50 flex justify-center items-center min-h-screen p-4">
    <div class="card w-full max-w-md bg-white shadow-2xl">
        <div class="card-body">
            <div class="flex flex-col items-center mb-6">
                <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" class="w-20 h-20 mb-2" />
                <h2 class="card-title text-2xl font-semibold text-gray-800">{{ $page->copy->text('LOGIN_TITLE') }}</h2>
            </div>

            @if ($page->error)
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $page->error }}</span>
                    </div>
                </div>
            @endif

            @if ($page->successMessage)
                <div class="alert alert-success shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $page->successMessage }}</span>
                    </div>
                </div>
            @endif

            @if (! $page->interactions->login)
            <p class="text-sm mb-4" role="note">Inaktive Laravel-Ansicht: Anmeldung noch nicht verbunden. Bestehender Einstieg bleibt login.php.</p>
            @endif
            <form method="post" action="login.php" class="space-y-4">
                <fieldset @disabled(! $page->interactions->login) class="space-y-4">
                @csrf

                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-gray-700">{{ $page->copy->text('USERNAME_LABEL') }}</span>
                    </label>
                    <input type="text" name="username" placeholder="Enter your username" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text text-gray-700">{{ $page->copy->text('PASSWORD_LABEL') }}</span>
                    </label>
                    <input type="password" name="password" placeholder="Enter your password" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                </div>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-lg">
                        {{ $page->copy->text('LOGIN_BUTTON') }}
                    </button>
                </div>
            </fieldset>
            </form>

            @if ($page->showSetup)
                <div class="text-center mt-4">
                    <a href="setup.php" class="link link-primary text-sm">{{ $page->copy->text('SETUP_LINK') }}</a>
                </div>
            @endif
            @if ($page->showRegistration)
                <div class="text-center mt-4">
                    <a href="register.php" class="link link-primary text-sm hover:underline">{{ $page->copy->text('REGISTER_BUTTON') }}</a>
                </div>
            @endif
        </div>
        <div class="text-center pb-6">
            <p class="text-xs text-gray-500">© 2024 Quodara Chrono - Zeiterfassung</p>
        </div>
    </div>
</body>

</html>
