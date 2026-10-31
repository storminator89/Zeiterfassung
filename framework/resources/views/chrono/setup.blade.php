<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'">
    <title>{{ $page->copy->text('SETUP_TITLE') }}</title>
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
                    <h1 class="h2 text-center mb-4">{{ $page->copy->text('SETUP_TITLE') }}</h1>
                    @if ($page->error)
                    <div class="alert alert-danger mb-3" role="alert">{{ $page->error }}</div>
                    @endif
                    <p class="text-secondary small mb-4" role="note">{{ $page->copy->text($page->requiresOperatorToken ? 'SETUP_NOTICE' : 'SETUP_LOCAL_FIRST_USER_NOTICE') }}</p>
                    <form method="post" action="setup.php">
                        <fieldset>
                            @csrf
                            @if ($page->requiresOperatorToken)
                            <div class="mb-3">
                                <label class="form-label" for="setup-token">{{ $page->copy->text('SETUP_TOKEN_LABEL') }}</label>
                                <input type="password" id="setup-token" name="setup_token" autocomplete="off" minlength="32" maxlength="256" class="form-control" required>
                            </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label" for="setup-username">{{ $page->copy->text('USERNAME_LABEL') }}</label>
                                <input type="text" id="setup-username" name="username" value="{{ $page::STANDARD_USERNAME }}" autocomplete="username" aria-describedby="setup-standard-admin" maxlength="100" class="form-control" required>
                                <p id="setup-standard-admin" class="form-hint">{{ $page->copy->text('SETUP_STANDARD_ADMIN_NOTICE') }}</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="setup-email">{{ $page->copy->text('FORM_EMAIL') }}</label>
                                <input type="email" id="setup-email" name="email" autocomplete="email" maxlength="254" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="setup-password">{{ $page->copy->text('SETUP_NEW_PASSWORD_LABEL') }}</label>
                                <input type="password" id="setup-password" name="password" autocomplete="new-password" aria-describedby="setup-password-hint" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="setup-confirmation">{{ $page->copy->text('SETUP_CONFIRM_PASSWORD') }}</label>
                                <input type="password" id="setup-confirmation" name="password_confirmation" autocomplete="new-password" aria-describedby="setup-password-hint" class="form-control" required>
                            </div>
                            <p id="setup-password-hint" class="form-hint">{{ $page->copy->text('SETUP_PASSWORD_HINT') }}</p>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">{{ $page->copy->text('SETUP_CREATE_ADMIN') }}</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </section>
            <p class="text-center mt-3"><a href="{{ $page->requiresOperatorToken ? 'login.php' : 'login.php?setup=complete' }}">{{ $page->copy->text('SETUP_BACK_LOGIN') }}</a></p>
        </div>
    </main>
</body>
</html>
