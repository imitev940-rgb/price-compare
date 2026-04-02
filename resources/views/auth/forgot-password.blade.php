<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceHunterPro Forgot Password</title>
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
        
        <!-- LEFT SIDE -->
        <div class="auth-left">
            <div class="auth-left-inner">
                <img src="{{ asset('images/logo.png') }}" alt="PriceHunterPro" class="auth-logo">

                <div class="auth-copy">
                    <h1>{{ __('messages.login_hero_title') }}</h1>

                    <p>
                        {{ __('messages.login_hero_text') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="auth-right">
            <div class="auth-card">

                <div class="auth-card-top">
                    <h2>Забравена парола</h2>
                </div>

                <!-- 🔥 ТУК Е САМО СЪОБЩЕНИЕ + БУТОН -->
                <div style="margin-top:20px; display:flex; flex-direction:column; gap:20px;">

                    <div style="
                        padding:16px;
                        border-radius:12px;
                        background:rgba(255,255,255,0.6);
                        border:1px solid rgba(0,0,0,0.06);
                        color:#6b7280;
                        font-size:14px;
                        line-height:1.6;
                    ">
                        При забравена парола, моля свържете се със своя системен администратор.
                    </div>
<a href="{{ route('login') }}" class="btn btn-primary w-100">
    Върни се обратно
</a>

                </div>

            </div>
        </div>

    </div>
</div>

<script>
    window.addEventListener('load', function () {
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.classList.add('hide');
            setTimeout(() => loader.remove(), 400);
        }
    });

    document.addEventListener('click', function (e) {
        const target = e.target.closest('a, button');
        if (target) {
            const loader = document.getElementById('global-loader');
            if (loader) {
                loader.classList.remove('hide');
            }
        }
    });
</script>

</body>
</html>