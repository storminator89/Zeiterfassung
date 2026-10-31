<!DOCTYPE html>
<html lang="{{ $page->copy->language }}" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'">
    <title>{{ $page->copy->text('LOGIN_TITLE') }}</title>
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
                    <h1 class="h2 text-center mb-4">{{ $page->copy->text('LOGIN_BUTTON') }}</h1>
                    @if ($page->error)
                    <div class="alert alert-danger mb-3" role="alert">{{ $page->error }}</div>
                    @endif
                    @if ($page->successMessage)
                    <div class="alert alert-success mb-3" role="status">{{ $page->successMessage }}</div>
                    @endif
                    <form method="post" action="login.php">
                        <fieldset @disabled(! $page->interactions->login)>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="login-username">{{ $page->copy->text('USERNAME_LABEL') }}</label>
                                <input type="text" id="login-username" name="username" autocomplete="username" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="login-password">{{ $page->copy->text('PASSWORD_LABEL') }}</label>
                                <input type="password" id="login-password" name="password" autocomplete="current-password" class="form-control" required>
                            </div>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">{{ $page->copy->text('LOGIN_BUTTON') }}</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </section>
            @if ($page->showSetup)
            <p class="text-center mt-3"><a href="setup.php">{{ $page->copy->text('SETUP_LINK') }}</a></p>
            @endif
            @if ($page->showRegistration)
            <p class="text-center mt-3"><a href="register.php">{{ $page->copy->text('REGISTER_BUTTON') }}</a></p>
            @endif
        </div>
    </main>
</body>
</html>
