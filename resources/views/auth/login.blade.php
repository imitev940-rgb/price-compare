<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceHunterPro Login</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>

<div class="auth-page">
    <div class="auth-glow auth-glow-1"></div>
    <div class="auth-glow auth-glow-2"></div>

    <div class="auth-shell">
        <div class="auth-left">
            <div class="auth-left-inner">
                <img src="{{ asset('images/logo.png') }}" alt="PriceHunterPro" class="auth-logo">

                <div class="auth-copy">
                    <h1>Smarter price tracking. Faster market decisions.</h1>

                    <p>
                        Monitor competitor prices, compare products, and react faster with a clean internal pricing dashboard.
                    </p>

                    <ul class="auth-features">
                        <li>Competitor monitoring</li>
                        <li>Automated price checks</li>
                        <li>Live price comparison</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <div class="auth-card-head">
                <h2>Welcome back</h2>
                <p>Sign in to access your PriceHunterPro dashboard.</p>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@company.com"
                    >
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="auth-row">
                    <label class="remember-wrap">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="forgot-link" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <button type="submit" class="primary-btn">Log in</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>