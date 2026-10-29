{{-- Existing login card; explicit operator-token first-admin setup, never public registration. --}}
<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'">
    <title>{{ $page->copy->text('SETUP_TITLE') }}</title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    @include('chrono.partials.auth-style')
</head>

<body class="chrono-auth bg-gradient-to-r from-blue-100 to-blue-50 flex justify-center items-center min-h-screen p-4">
    <div class="card w-full max-w-md bg-white shadow-2xl">
        <div class="card-body">
            <div class="flex flex-col items-center mb-6">
                <img src="assets/kolibri_icon.png" alt="Quodara Chrono Logo" class="w-20 h-20 mb-2" />
                <h2 class="card-title text-2xl font-semibold text-gray-800">{{ $page->copy->text('SETUP_TITLE') }}</h2>
            </div>

            @if ($page->error)
                <div class="alert alert-error shadow-lg mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>{{ $page->error }}</span>
                    </div>
                </div>
            @endif

            <p class="text-sm text-gray-700 mb-4" role="note">{{ $page->copy->text('SETUP_NOTICE') }}</p>
            <form method="post" action="setup.php" class="space-y-4">
                <fieldset class="space-y-4">
                @csrf

                <div class="form-control">
                    <label class="label" for="setup-token"><span class="label-text text-gray-700">{{ $page->copy->text('SETUP_TOKEN_LABEL') }}</span></label>
                    <input type="password" id="setup-token" name="setup_token" autocomplete="off" minlength="32" maxlength="256" class="input input-bordered w-full bg-gray-50 focus:bg-white" required />
                </div>
                <div class="form-control">
                    <label class="label" for="setup-username">
                        <span class="label-text text-gray-700">{{ $page->copy->text('USERNAME_LABEL') }}</span>
                    </label>
                    <input type="text" id="setup-username" name="username" value="{{ $page::STANDARD_USERNAME }}" autocomplete="username" aria-describedby="setup-standard-admin" maxlength="100" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                    <p id="setup-standard-admin" class="text-xs text-gray-600">{{ $page->copy->text('SETUP_STANDARD_ADMIN_NOTICE') }}</p>
                </div>

                <div class="form-control">
                    <label class="label" for="setup-password">
                        <span class="label-text text-gray-700">{{ $page->copy->text('SETUP_NEW_PASSWORD_LABEL') }}</span>
                    </label>
                    <input type="password" id="setup-password" name="password" autocomplete="new-password" class="input input-bordered w-full bg-gray-50 focus:bg-white transition-colors duration-300" required />
                </div>

                <div class="form-control">
                    <label class="label" for="setup-email"><span class="label-text text-gray-700">{{ $page->copy->text('FORM_EMAIL') }}</span></label>
                    <input type="email" id="setup-email" name="email" autocomplete="email" maxlength="254" class="input input-bordered w-full bg-gray-50 focus:bg-white" required />
                </div>
                <div class="form-control">
                    <label class="label" for="setup-confirmation"><span class="label-text text-gray-700">{{ $page->copy->text('SETUP_CONFIRM_PASSWORD') }}</span></label>
                    <input type="password" id="setup-confirmation" name="password_confirmation" autocomplete="new-password" class="input input-bordered w-full bg-gray-50 focus:bg-white" required />
                </div>
                <p class="text-xs text-gray-600">{{ $page->copy->text('SETUP_PASSWORD_HINT') }}</p>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-lg">
                        {{ $page->copy->text('SETUP_CREATE_ADMIN') }}
                    </button>
                </div>
            </fieldset>
            </form>

            <div class="text-center mt-4"><a href="login.php" class="link link-primary text-sm">{{ $page->copy->text('SETUP_BACK_LOGIN') }}</a></div>
        </div>
        <div class="text-center pb-6">
            <p class="text-xs text-gray-500">© 2024 Quodara Chrono - Zeiterfassung</p>
        </div>
    </div>
</body>

</html>
