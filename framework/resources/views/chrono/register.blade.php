<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'">
    <title>{{ $page->copy->text('REGISTRATION_TITLE') }}</title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">
    <link rel="stylesheet" href="assets/vendor/tabler/1.7.1/tabler.min.css">
    <link rel="stylesheet" href="assets/css/app_ui.css">
</head>
<body class="chrono-auth d-flex flex-column">
    <main class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <span class="navbar-brand navbar-brand-autodark d-inline-flex align-items-center gap-2">
                    <img src="assets/kolibri_icon.png" width="36" height="36" alt="">
                    Quodara Chrono
                </span>
            </div>
            <section class="card card-md">
                <div class="card-body">
                    <h1 class="h2 text-center mb-4">{{ $page->copy->text('REGISTER_BUTTON') }}</h1>
                    @if ($page->error)
                    <div class="alert alert-danger mb-3" role="alert">{{ $page->error }}</div>
                    @endif
                    <form method="post" action="register.php">
                        <fieldset>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="registration-username">{{ $page->copy->text('USERNAME_LABEL') }}</label>
                                <input type="text" id="registration-username" name="username" autocomplete="username" maxlength="100" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="registration-email">{{ $page->copy->text('FORM_EMAIL') }}</label>
                                <input type="email" id="registration-email" name="email" autocomplete="email" maxlength="254" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="registration-password">{{ $page->copy->text('PASSWORD_LABEL') }}</label>
                                <input type="password" id="registration-password" name="password" autocomplete="new-password" minlength="12" maxlength="72" class="form-control" aria-describedby="registration-password-hint" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="registration-confirmation">{{ $page->copy->text('REGISTRATION_CONFIRM_PASSWORD') }}</label>
                                <input type="password" id="registration-confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" class="form-control" aria-describedby="registration-password-hint" required>
                            </div>
                            <p id="registration-password-hint" class="form-hint">{{ $page->copy->text('REGISTRATION_PASSWORD_HINT') }}</p>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">{{ $page->copy->text('REGISTER_BUTTON') }}</button>
                            </div>
                        </fieldset>
                    </form>
                    <p class="text-secondary small mt-4 mb-0" role="note">{{ $page->copy->text('REGISTRATION_NOTICE') }}</p>
                </div>
            </section>
            <p class="text-center mt-3"><a href="login.php">{{ $page->copy->text('REGISTRATION_BACK_LOGIN') }}</a></p>
        </div>
    </main>
</body>
</html>
