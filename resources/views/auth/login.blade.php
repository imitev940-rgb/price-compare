<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PriceHunterPro Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- ✅ Typography like Image 1: Inter for UI + Syne for headlines -->
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root{
      /* page background */
      --page-bg:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.18), transparent 28%),
        radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.12), transparent 28%),
        linear-gradient(135deg, #071224 0%, #0c1b33 45%, #102448 100%);

      /* used inside right panel only */
      --forgot-bg:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.18), transparent 28%),
        radial-gradient(circle at bottom right, rgba(34, 197, 94, 0.12), transparent 28%),
        linear-gradient(135deg, rgba(7,18,36,0.92) 0%, rgba(12,27,51,0.92) 45%, rgba(16,36,72,0.92) 100%);

      /* glass panels */
      --glass: rgba(8,16,32,0.88);
      --glassBorder: rgba(255,255,255,0.10);
      --glassBorderSoft: rgba(255,255,255,0.07);
      --shadow:
        0 40px 100px rgba(0,0,0,0.55),
        0 0 0 1px rgba(255,255,255,0.04) inset,
        0 1px 0 rgba(255,255,255,0.10) inset;

      --muted: rgba(255,255,255,0.60);
      --muted2: rgba(255,255,255,0.45);

      --primaryA:#2563eb;
      --primaryB:#1d4ed8;

      --pillBg: rgba(34,197,94,0.10);
      --pillBorder: rgba(34,197,94,0.28);
      --pillText: rgba(110,255,175,0.95);
      --pillDot: #22c55e;
    }

    html, body{
      height: 100%;
      font-family: Inter, Arial, sans-serif;
      overflow: hidden;
      background: #071224;
    }

    /* headings/buttons use Syne (like Image 1) */
    .hero h1,
    .right h2,
    .btn{
      font-family: 'Syne', Inter, Arial, sans-serif;
    }

    /* page wrapper */
    .auth-page{
      min-height: 100vh;
      position: relative;
      overflow: hidden;
      background: var(--page-bg);
      display:flex;
      align-items:center;
      justify-content:center;
      padding: 32px 52px;
    }

    /* subtle page glows */
    .auth-glow{
      position: absolute;
      border-radius: 999px;
      filter: blur(14px);
      pointer-events: none;
      z-index: 0;
    }
    .auth-glow-1{
      width: 300px; height: 300px;
      background: rgba(37, 99, 235, 0.18);
      top: 60px; left: 80px;
    }
    .auth-glow-2{
      width: 360px; height: 360px;
      background: rgba(34, 197, 94, 0.10);
      bottom: 40px; right: 70px;
    }

    /* ticker */
    .ticker-bar{
      position:fixed; bottom:0; left:0; right:0;
      height:42px;
      background: rgba(4,10,22,0.90);
      border-top:1px solid rgba(255,255,255,0.07);
      backdrop-filter: blur(12px);
      overflow:hidden;
      z-index:9999;
      display:flex;
      align-items:center;
      font-family: Inter, Arial, sans-serif;
    }
    .ticker-track{ display:flex; align-items:center; white-space:nowrap; animation:tickerScroll 30s linear infinite; }
    .ticker-item{
      display:inline-flex; align-items:center; gap:8px;
      padding:0 28px;
      font-size:13px; font-weight:500;
      color: rgba(255,255,255,0.55);
      border-right:1px solid rgba(255,255,255,0.07);
    }
    .t-dot{ width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .t-dot.up{ background:#22c55e; box-shadow:0 0 5px rgba(34,197,94,0.7); }
    .t-dot.dn{ background:#ef4444; box-shadow:0 0 5px rgba(239,68,68,0.7); }
    .t-pct.up{ color:#4ade80; font-weight:700; }
    .t-pct.dn{ color:#f87171; font-weight:700; }
    @keyframes tickerScroll{ 0%{ transform:translateX(0);} 100%{ transform:translateX(-50%);} }

    /* layout */
    .shell{
      width: 100%;
      max-width: 1200px;
      display:grid;
      grid-template-columns: 1fr 460px;
      gap: 34px;
      align-items:center;
      position: relative;
      z-index: 2;
    }

    /* base panel (used by RIGHT) */
    .panel{
      background: var(--glass);
      border: 1px solid var(--glassBorder);
      border-radius: 28px;
      padding: 26px;
      backdrop-filter: blur(32px) saturate(180%);
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;

      display:flex;
      flex-direction:column;
      justify-content:flex-start;
    }
    .panel::before{
      content:'';
      position:absolute; top:0; left:0; right:0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(96,165,250,0.40), rgba(167,139,250,0.35), transparent);
      pointer-events:none;
      z-index: 0;
    }

    /* RIGHT */
    .panel-right{
      min-height: 560px;
    }
    .panel-right::after{
      content:'';
      position:absolute;
      inset:0;
      background: var(--forgot-bg);
      opacity: 0.55;
      pointer-events:none;
      z-index: 0;
    }
    .panel-right > *{ position: relative; z-index: 1; }

    /* LEFT = no separate card look */
    .panel-left{
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      backdrop-filter: none !important;
      padding: 6px 6px 6px 0 !important;
      border-radius: 0 !important;
      overflow: visible !important;
      min-height: unset !important;
    }
    .panel-left::before,
    .panel-left::after{
      content: none !important;
    }

    /* pill + animated dot */
    .pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 7px 16px;
      border-radius: 999px;
      background: var(--pillBg);
      border: 1px solid var(--pillBorder);
      color: var(--pillText);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.10em;
      text-transform: uppercase;
      width: fit-content;
      margin-bottom: 14px;
      white-space: nowrap;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
    }
    .pill-dot{
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--pillDot);
      box-shadow: 0 0 10px rgba(34,197,94,0.85);
      flex-shrink: 0;
      animation: pillPulse 1.6s ease-in-out infinite;
    }
    @keyframes pillPulse{
      0%, 100%{ transform: scale(1); opacity: 1; box-shadow: 0 0 10px rgba(34,197,94,0.75); }
      50%{ transform: scale(1.6); opacity: 0.45; box-shadow: 0 0 18px rgba(34,197,94,0.95); }
    }
    @media (prefers-reduced-motion: reduce){
      .pill-dot{ animation: none; }
    }

    /* left content */
    .hero{ margin-bottom: 18px; }
    .hero p{ font-family: Inter, Arial, sans-serif; }

    .hero h1{
      font-weight: 800;
      font-size: clamp(34px, 3.1vw, 54px);
      line-height: 1.04;
      letter-spacing: -0.04em;
      color: #ffffff;
      margin-bottom: 10px;
      max-width: 26ch;
    }
    .hero p{
      color: rgba(255,255,255,0.70);
      font-size: 14.5px;
      line-height: 1.75;
      max-width: 70ch;
      font-weight: 500; /* ✅ closer to Image 1 */
    }

    .features{
      margin-top: 14px;
      display:flex;
      flex-direction:column;
      gap:10px;
    }
    .feat{
      display:flex;
      align-items:center;
      gap:14px;
      padding: 12px 14px;
      background: rgba(255,255,255,0.035);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 16px;
      transition: all .22s ease;
    }
    .feat:hover{
      background: rgba(255,255,255,0.06);
      border-color: rgba(255,255,255,0.12);
      transform: translateX(6px);
    }
    .feat-ic{
      width: 40px; height: 40px;
      border-radius: 12px;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(37,99,235,0.16);
      color: rgba(255,255,255,0.92);
      flex-shrink:0;
      font-size: 16px;
      font-weight: 900;
      font-family: Inter, Arial, sans-serif;
    }
    .feat-title{
      font-size: 14px;
      font-weight: 800;
      color: rgba(255,255,255,0.90);
      letter-spacing: -0.01em;
      font-family: Inter, Arial, sans-serif;
    }

    /* right header */
    .right-top{
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap:14px;
      margin-bottom: 18px;
    }
    .right-brand img{
      width: 220px;
      max-width: 100%;
      display:block;
      filter: drop-shadow(0 10px 26px rgba(0,0,0,0.28));
    }

    /* ✅ Fix the "Български" dropdown (Image 1): ensure Inter + remove weird inheritance */
    .language-select{
      width: 160px;
      height: 36px;
      padding: 0 34px 0 14px; /* a bit more left padding */
      appearance: none;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px;
      font-family: Inter, Arial, sans-serif;  /* ✅ */
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0;                     /* ✅ */
      color: rgba(255,255,255,0.85);
      cursor: pointer;
      outline: none;
      transition: all .2s ease;
      position: relative;
      z-index: 1;
      background:
        rgba(255,255,255,0.06)
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='rgba(255,255,255,0.55)' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E")
        no-repeat right 12px center;
    }
    .language-select:hover{
      background-color: rgba(255,255,255,0.10);
      border-color: rgba(255,255,255,0.22);
    }
    .language-select option{
      font-family: Inter, Arial, sans-serif;
      background:#0c1b33;
      color:#fff;
    }

    .right h2{
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #fff;
      margin: 6px 0 6px;
      position: relative;
      z-index: 1;
    }
    .right .sub{
      color: var(--muted2);
      font-size: 13px;
      line-height: 1.55;
      margin-bottom: 16px;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
    }

    /* form */
    .field{ margin-bottom:12px; }
    .field label{
      display:block;
      font-size:12px;
      font-weight:800;
      color: rgba(255,255,255,0.55);
      margin-bottom:6px;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
    }
    .input{
      width:100%;
      height:46px;
      padding:0 14px;
      border-radius:13px;
      background: rgba(255,255,255,0.05);
      border:1px solid rgba(255,255,255,0.10);
      color:#fff;
      font-size:14px;
      outline:none;
      transition: all .22s ease;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
      font-weight: 600;
    }
    .input::placeholder{ color: rgba(255,255,255,0.22); font-weight: 500; }
    .input:hover{ background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.18); }
    .input:focus{
      background: rgba(37,99,235,0.09);
      border-color: rgba(96,165,250,0.55);
      box-shadow: 0 0 0 3px rgba(37,99,235,0.14);
    }

    .row{
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 12px;
      margin: 6px 0 14px;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
    }
    .remember{
      display:flex;
      align-items:center;
      gap: 10px;
      color: rgba(255,255,255,0.55);
      font-weight: 700;
      font-size: 13px;
      user-select:none;
    }
    .remember input{ width:16px; height:16px; }

    .forgot{
      color: #60a5fa;
      text-decoration:none;
      font-size: 13px;
      font-weight: 800;
      font-family: Inter, Arial, sans-serif;
    }
    .forgot:hover{ text-decoration:underline; color:#93c5fd; }

    .btn{
      width: 100%;
      height: 48px;
      border: 0;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--primaryA) 0%, var(--primaryB) 100%);
      color: #fff;
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 18px 44px rgba(37,99,235,0.32);
      transition: all .22s ease;
      position: relative;
      z-index: 1;
    }
    .btn:hover{
      transform: translateY(-2px);
      box-shadow: 0 24px 64px rgba(37,99,235,0.42);
    }

    .trust{
      margin-top:auto;
      padding-top:16px;
      text-align:center;
      color: rgba(255,255,255,0.25);
      font-size:10px;
      font-weight:800;
      letter-spacing:0.12em;
      text-transform:uppercase;
      position: relative;
      z-index: 1;
      font-family: Inter, Arial, sans-serif;
    }
    .trust-flags{
      display:flex;
      justify-content:center;
      gap:6px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .flag-pill{
      width:28px; height:19px;
      border-radius:4px;
      overflow:hidden;
      border:1px solid rgba(255,255,255,0.12);
      opacity:.8;
    }

    @media (max-width: 1100px){
      html, body{ overflow:auto; }
      .auth-page{ padding: 18px 14px 74px; align-items:flex-start; }
      .shell{ grid-template-columns: 1fr; }
      .panel-right{ min-height: auto; }
      .panel-left{ padding: 0 !important; }
    }
  </style>
</head>

<body>


  <div class="auth-page">
    <div class="auth-glow auth-glow-1"></div>
    <div class="auth-glow auth-glow-2"></div>

    <div class="shell">
      <!-- LEFT -->
      <section class="panel panel-left">
        <div class="pill"><span class="pill-dot"></span>Конкурентен анализ на цени</div>

        <div class="hero">
          <h1>{{ __('messages.login_hero_title') }}</h1>
          <p>{{ __('messages.login_hero_text') }}</p>
        </div>

        <div class="features">
          <div class="feat">
            <div class="feat-ic">👁</div>
            <div class="feat-title">{{ __('messages.feature_competitor_monitoring') }}</div>
          </div>
          <div class="feat">
            <div class="feat-ic">⚡</div>
            <div class="feat-title">{{ __('messages.feature_automated_price_checks') }}</div>
          </div>
          <div class="feat">
            <div class="feat-ic">$</div>
            <div class="feat-title">{{ __('messages.feature_live_price_comparison') }}</div>
          </div>
        </div>
      </section>

      <!-- RIGHT -->
      <section class="panel panel-right right">
        <div class="right-top">
          <div class="right-brand">
            <img src="{{ asset('images/logo.png') }}" alt="PriceHunterPro" />
          </div>

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

        <h2>{{ __('messages.welcome_back') }}</h2>
        <div class="sub">{{ __('messages.sign_in_dashboard') }}</div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}">
          @csrf

          <div class="field">
            <label for="email">{{ __('messages.email') }}</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}"
              required autofocus autocomplete="username" placeholder="{{ __('messages.email_placeholder') }}" />
            <x-input-error :messages="$errors->get('email')" />
          </div>

          <div class="field">
            <label for="password">{{ __('messages.password') }}</label>
            <input class="input" id="password" type="password" name="password"
              required autocomplete="current-password" placeholder="{{ __('messages.password_placeholder') }}" />
            <x-input-error :messages="$errors->get('password')" />
          </div>

          <div class="row">
            <label class="remember">
              <input type="checkbox" name="remember" />
              <span>{{ __('messages.remember_me') }}</span>
            </label>

            @if (Route::has('password.request'))
              <a class="forgot" href="{{ route('password.request') }}">{{ __('messages.forgot_password') }}</a>
            @endif
          </div>

          <button class="btn" type="submit">{{ __('messages.log_in') }}</button>
        </form>

        <div class="trust">
          Доверен от търговци в цяла Европа
          <div class="trust-flags">
            <div class="flag-pill" title="България">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="32" height="7.33" y="0" fill="#fff"/><rect width="32" height="7.33" y="7.33" fill="#009B77"/><rect width="32" height="7.33" y="14.67" fill="#D62612"/></svg>
            </div>
            <div class="flag-pill" title="Германия">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="32" height="7.33" y="0" fill="#000"/><rect width="32" height="7.33" y="7.33" fill="#DD0000"/><rect width="32" height="7.33" y="14.67" fill="#FFCE00"/></svg>
            </div>
            <div class="flag-pill" title="Франция">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="10.67" height="22" x="0" fill="#002395"/><rect width="10.67" height="22" x="10.67" fill="#fff"/><rect width="10.67" height="22" x="21.33" fill="#ED2939"/></svg>
            </div>
            <div class="flag-pill" title="Румъния">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="10.67" height="22" x="0" fill="#002B7F"/><rect width="10.67" height="22" x="10.67" fill="#FCD116"/><rect width="10.67" height="22" x="21.33" fill="#CE1126"/></svg>
            </div>
            <div class="flag-pill" title="Турция">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="32" height="22" fill="#E30A17"/><circle cx="13" cy="11" r="5.5" fill="#fff"/><circle cx="15" cy="11" r="4.2" fill="#E30A17"/></svg>
            </div>
            <div class="flag-pill" title="Испания">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="32" height="22" fill="#c60b1e"/><rect width="32" height="11" y="5.5" fill="#ffc400"/></svg>
            </div>
            <div class="flag-pill" title="Европейски съюз">
              <svg width="32" height="22" viewBox="0 0 32 22"><rect width="32" height="22" fill="#003399"/><text x="16" y="15" text-anchor="middle" font-size="9" fill="#FFCC00" font-family="serif">★★★</text></svg>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</body>
</html>