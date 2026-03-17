<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceHunterPro</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    <!-- Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-top">
            <img src="{{ asset('images/logo.png') }}" alt="PriceHunterPro" class="sidebar-logo">
        </div>

        <nav class="sidebar-nav">

            <a href="/comparison" class="{{ request()->is('comparison') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="layout-dashboard"></i>
                    Dashboard
                </span>
            </a>

            <a href="/products" class="{{ request()->is('products*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="package"></i>
                    Products
                </span>
            </a>

            <a href="/stores" class="{{ request()->is('stores*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="store"></i>
                    Stores
                </span>
            </a>

            <a href="/links" class="{{ request()->is('links*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="link"></i>
                    Competitor Links
                </span>
            </a>

            <a href="/price-history" class="{{ request()->is('price-history*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="chart-column"></i>
                    Price History
                </span>
            </a>

        </nav>

        <div class="sidebar-bottom"></div>
    </aside>

    <main class="main-panel">
        <div class="topbar">
            <div>
                <div class="topbar-title">PriceHunterPro</div>
                <div class="topbar-subtitle">Competitor pricing dashboard</div>
            </div>

            <div class="topbar-right">
                <button class="topbar-icon" type="button">🔔</button>

                <div class="user-menu">
                    <button class="user-btn" type="button">
                        <span class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                    </button>

                    <div class="user-dropdown">
                        <a href="#">Profile</a>
                        <a href="#">Settings</a>

                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-logout-btn">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            @yield('content')
        </div>

        <footer class="dashboard-footer">
            <div>© {{ date('Y') }} PriceHunterPro</div>
            <div>Version 1.0</div>
            <div>Created by <strong>SITEZZY – Ivan Mitev</strong></div>
        </footer>

        <button id="scrollTopBtn">↑</button>

        <script>
            const scrollBtn = document.getElementById("scrollTopBtn");

            window.addEventListener("scroll", function () {
                if (window.scrollY > 10) {
                    scrollBtn.classList.add("show");
                } else {
                    scrollBtn.classList.remove("show");
                }
            });

            scrollBtn.onclick = function () {
                window.scrollTo({ top: 0, behavior: "smooth" });
            };

            // activate icons
            lucide.createIcons();
        </script>
    </main>
</div>

</body>
</html>