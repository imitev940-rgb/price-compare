<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceHunterPro Login</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>

<div id="global-loader" class="global-loader">
    <div class="loader-box">
        <div class="spinner"></div>
        <div class="loader-text">{{ __('messages.loading') }}</div>
    </div>
</div>

<div class="auth-page">
    <div class="auth-glow auth-glow-1"></div>
    <div class="auth-glow auth-glow-2"></div>

    <div class="auth-shell">
        <div class="auth-left">
            <div class="auth-left-inner">
                <img src="{{ asset('images/logo.png') }}" alt="PriceHunterPro" class="auth-logo">

                <div class="auth-copy">
                    <h1>{{ __('messages.login_hero_title') }}</h1>

                    <p>
                        {{ __('messages.login_hero_text') }}
                    </p>

                    <ul class="auth-features">
                        <li>{{ __('messages.feature_competitor_monitoring') }}</li>
                        <li>{{ __('messages.feature_automated_price_checks') }}</li>
                        <li>{{ __('messages.feature_live_price_comparison') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="auth-card">
            <div class="auth-login-top">
                <div class="language-switcher auth-language-switcher">
                    <select class="language-select" onchange="window.location.href=this.value">
                        <option value="{{ route('lang.switch', 'bg') }}" {{ app()->getLocale() == 'bg' ? 'selected' : '' }}>Български</option>
                        <option value="{{ route('lang.switch', 'en') }}" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
                        <option value="{{ route('lang.switch', 'de') }}" {{ app()->getLocale() == 'de' ? 'selected' : '' }}>Deutsch</option>
                        <option value="{{ route('lang.switch', 'fr') }}" {{ app()->getLocale() == 'fr' ? 'selected' : '' }}>Français</option>
                        <option value="{{ route('lang.switch', 'es') }}" {{ app()->getLocale() == 'es' ? 'selected' : '' }}>Español</option>
                        <option value="{{ route('lang.switch', 'ro') }}" {{ app()->getLocale() == 'ro' ? 'selected' : '' }}>Română</option>
                        <option value="{{ route('lang.switch', 'tr') }}" {{ app()->getLocale() == 'tr' ? 'selected' : '' }}>Türkçe</option>
                    </select>
                </div>
            </div>

            <div class="auth-card-head">
                <h2>{{ __('messages.welcome_back') }}</h2>
                <p>{{ __('messages.sign_in_dashboard') }}</p>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="auth-form loader-form">
                @csrf

                <div class="field">
                    <label for="email">{{ __('messages.email') }}</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="{{ __('messages.email_placeholder') }}"
                    >
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="field">
                    <label for="password">{{ __('messages.password') }}</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="{{ __('messages.password_placeholder') }}"
                    >
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="auth-row">
                    <label class="remember-wrap">
                        <input type="checkbox" name="remember">
                        <span>{{ __('messages.remember_me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="forgot-link" href="{{ route('password.request') }}">
                            {{ __('messages.forgot_password') }}
                        </a>
                    @endif
                </div>

                <button type="submit" class="primary-btn">{{ __('messages.log_in') }}</button>
            </form>
        </div>
    </div>
</div>

<script>
    const loader = document.getElementById('global-loader');

    function showLoader() {
        if (loader) {
            loader.classList.remove('hidden');
        }
    }

    function hideLoader() {
        if (loader) {
            loader.classList.add('hidden');
        }
    }

    window.addEventListener('load', function () {
        hideLoader();
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.loader-form').forEach(form => {
            form.addEventListener('submit', function () {
                showLoader();
            });
        });

        document.querySelectorAll('[data-loader="true"]').forEach(el => {
            el.addEventListener('click', function () {
                showLoader();
            });
        });
    });
</script>

</body>
</html>