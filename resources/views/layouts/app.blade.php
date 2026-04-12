@php
    use App\Models\Setting;

    $systemName = Setting::getValue('system_name', 'PriceHunterPro');
    $footerVersion = Setting::getValue('footer_version', '8.0');
    $createdBy = Setting::getValue('created_by', 'SITEZZY – Ivan Mitev');
    $notificationRefreshInterval = (int) Setting::getValue('notification_refresh_interval', 10);
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $systemName }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
            }
        })();
    </script>
</head>
<body>

<div id="global-loader" class="global-loader">
    <div class="loader-box">
        <div class="spinner"></div>
        <div class="loader-text">{{ __('messages.loading') }}</div>
    </div>
</div>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="/comparison">
                <img src="{{ asset('images/logo.png') }}" alt="{{ $systemName }}" class="sidebar-logo">
            </a>
        </div>

        <nav class="sidebar-nav">
            <a href="/comparison" class="{{ request()->is('comparison') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="layout-dashboard"></i>
                    {{ __('messages.dashboard') }}
                </span>
            </a>

            <a href="/products" class="{{ request()->is('products*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="package"></i>
                    {{ __('messages.products') }}
                </span>
            </a>

            <a href="/stores" class="{{ request()->is('stores*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="store"></i>
                    {{ __('messages.stores') }}
                </span>
            </a>

            <a href="/links" class="{{ request()->is('links*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="link"></i>
                    {{ __('messages.competitor_links') }}
                </span>
            </a>

            <a href="/price-history" class="{{ request()->is('price-history*') ? 'active' : '' }}">
                <span class="nav-item">
                    <i data-lucide="chart-column"></i>
                    {{ __('messages.price_history') }}
                </span>
            </a>
        </nav>

        <div class="sidebar-bottom"></div>
    </aside>

    <main class="main-panel">
        <div class="topbar">
            <div>
                <div class="topbar-title">{{ $systemName }}</div>
                <div class="topbar-subtitle">{{ __('messages.competitor_pricing_dashboard') }}</div>
            </div>

            <div class="topbar-right">
                <div class="language-switcher">
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

                <button class="topbar-icon" id="themeToggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode">
                    <i data-lucide="moon"></i>
                </button>

                <div class="notification-wrapper" id="notificationWrapper">
                    <button class="topbar-icon notification-btn" id="notificationBell" type="button" aria-label="Notifications">
                        <i data-lucide="bell"></i>
                        <span id="notificationCount" class="notification-count">0</span>
                    </button>

                    <div id="notificationDropdown" class="notification-dropdown">
                        <div class="notification-dropdown-header">
                            <div class="notification-dropdown-title">
                                {{ __('messages.notifications') }}
                            </div>

                            <button id="markAllNotificationsRead"
                                    class="mark-all-read-btn"
                                    type="button">
                                Clear all
                            </button>
                        </div>

                        <div id="notificationList" class="notification-list">
                            <div class="notification-loading">
                                {{ __('messages.loading') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="user-menu" id="userMenu">
                    <button class="user-btn" id="userMenuToggle" type="button" aria-haspopup="true" aria-expanded="false">
                        <span class="user-avatar">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                        <i data-lucide="chevron-down" class="user-btn-chevron"></i>
                    </button>

                    <div class="user-dropdown" id="userDropdown">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item">
                            <i data-lucide="user"></i>
                            <span>{{ __('messages.profile') }}</span>
                        </a>

                        <a href="{{ route('settings.edit') }}" class="dropdown-item">
                            <i data-lucide="settings"></i>
                            <span>{{ __('messages.settings') }}</span>
                        </a>

                        @if(auth()->user()->isAdminLevel())
                            <div class="dropdown-divider"></div>

                            <a href="{{ route('admin.users.index') }}" class="dropdown-item">
                                <i data-lucide="users"></i>
                                <span>Users</span>
                            </a>

                            @if(auth()->user()->isSuperAdmin())
                                <a href="{{ route('admin.system-settings.edit') }}" class="dropdown-item">
                                    <i data-lucide="sliders"></i>
                                    <span>System Settings</span>
                                </a>
                            @endif
                        @endif

                        <div class="dropdown-divider"></div>

                        <form method="POST" action="{{ route('logout') }}" class="loader-form">
                            @csrf
                            <button type="submit" class="dropdown-logout-btn">
                                <i data-lucide="log-out"></i>
                                <span>{{ __('messages.logout') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-card">
            @yield('content')
        </div>

        <footer class="dashboard-footer">
            <div>© {{ date('Y') }} {{ $systemName }}</div>
            <div>{{ __('messages.version') }} {{ $footerVersion }}</div>
            <div>{{ __('messages.created_by') }} <strong>{{ $createdBy }}</strong></div>
        </footer>

        <button id="scrollTopBtn">↑</button>
    </main>
</div>

<script>
    const scrollBtn = document.getElementById('scrollTopBtn');
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationWrapper = document.getElementById('notificationWrapper');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const markAllNotificationsReadBtn = document.getElementById('markAllNotificationsRead');
    const loader = document.getElementById('global-loader');
    const themeToggle = document.getElementById('themeToggle');
    const userMenu = document.getElementById('userMenu');
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdown = document.getElementById('userDropdown');

    let previousNotificationIds = [];
    let initialNotificationsLoaded = false;
    let audioUnlocked = false;

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

    function applyTheme(theme) {
        const isDark = theme === 'dark';

        document.documentElement.classList.toggle('dark-mode', isDark);
        document.body.classList.toggle('dark-mode', isDark);

        if (themeToggle) {
            themeToggle.innerHTML = isDark
                ? '<i data-lucide="sun"></i>'
                : '<i data-lucide="moon"></i>';
        }

        lucide.createIcons();
    }

    function toggleTheme() {
        const isDark = document.body.classList.contains('dark-mode');
        const newTheme = isDark ? 'light' : 'dark';

        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function formatNotificationDate(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);

        if (isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleString();
    }

    function updateNotificationCount(unreadCount) {
        if (!notificationCount || !notificationBell) return;

        if (unreadCount > 0) {
            notificationCount.style.display = 'inline-flex';
            notificationCount.textContent = unreadCount > 99 ? '99+' : unreadCount;
            notificationBell.classList.add('has-unread');
        } else {
            notificationCount.style.display = 'none';
            notificationCount.textContent = '0';
            notificationBell.classList.remove('has-unread');
        }

        if (markAllNotificationsReadBtn) {
            markAllNotificationsReadBtn.disabled = unreadCount <= 0;
        }
    }

    function unlockAudio() {
        audioUnlocked = true;
    }

    function playNotificationSound() {
        if (!audioUnlocked) return;

        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;

            if (!AudioContextClass) return;

            const context = new AudioContextClass();
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, context.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(660, context.currentTime + 0.18);

            gain.gain.setValueAtTime(0.0001, context.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.08, context.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);

            oscillator.connect(gain);
            gain.connect(context.destination);

            oscillator.start(context.currentTime);
            oscillator.stop(context.currentTime + 0.22);

            oscillator.onended = () => {
                if (typeof context.close === 'function') {
                    context.close();
                }
            };
        } catch (error) {
            console.error('Notification sound failed', error);
        }
    }

    function pulseBell() {
        if (!notificationBell) return;

        notificationBell.classList.remove('ringing');
        void notificationBell.offsetWidth;
        notificationBell.classList.add('ringing');

        setTimeout(() => {
            notificationBell.classList.remove('ringing');
        }, 900);
    }

    function renderNotifications(notifications, newIds = []) {
        if (!notificationList) return;

        if (!notifications || !notifications.length) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    No notifications yet
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notification => {
            const createdAt = formatNotificationDate(notification.created_at);
            const message = escapeHtml(notification.message || '');
            const isNew = newIds.includes(notification.id);

            // Detect price direction
            let dirClass = '';
            let arrow = '';
            const match = message.match(/([\d,.]+)\s*€\s*->\s*([\d,.]+)\s*€/);
            if (match) {
                const oldP = parseFloat(match[1].replace(',', '.'));
                const newP = parseFloat(match[2].replace(',', '.'));
                if (newP > oldP) {
                    dirClass = 'notif-up';
                    arrow = '▲ ';
                } else if (newP < oldP) {
                    dirClass = 'notif-down';
                    arrow = '▼ ';
                }
            }

            return `
                <div class="notification-item ${notification.is_read ? 'is-read' : 'is-unread'} ${isNew ? 'is-new-highlight' : ''} ${dirClass}">
                    <div class="notification-message">${arrow}${message}</div>
                    <div class="notification-date">${createdAt}</div>
                </div>
            `;
        }).join('');
    }

    async function loadNotifications(options = {}) {
        const { silent = false } = options;

        try {
            const response = await fetch('{{ route('notifications.index') }}', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load notifications');
            }

            const data = await response.json();
            const notifications = data.notifications || [];
            const unreadCount = data.unread_count || 0;
            const currentIds = notifications.map(item => item.id);
            let newIds = [];

            if (initialNotificationsLoaded) {
                newIds = currentIds.filter(id => !previousNotificationIds.includes(id));
            }

            updateNotificationCount(unreadCount);
            renderNotifications(notifications, newIds);

            if (initialNotificationsLoaded && newIds.length > 0 && !silent) {
                pulseBell();
                playNotificationSound();
            }

            previousNotificationIds = currentIds;
            initialNotificationsLoaded = true;
        } catch (error) {
            console.error(error);

            if (notificationList) {
                notificationList.innerHTML = `
                    <div class="notification-error">
                        Error loading notifications
                    </div>
                `;
            }
        }
    }

    async function clearNotifications() {
        try {
            const response = await fetch('{{ route('notifications.clear') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to clear notifications');
            }

            return true;
        } catch (error) {
            console.error('Failed to clear notifications', error);
            return false;
        }
    }

    window.addEventListener('load', function () {
        hideLoader();
    });

    document.addEventListener('click', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    document.addEventListener('DOMContentLoaded', function () {
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', toggleTheme);
        }

        if (scrollBtn) {
            window.addEventListener('scroll', function () {
                if (window.scrollY > 10) {
                    scrollBtn.classList.add('show');
                } else {
                    scrollBtn.classList.remove('show');
                }
            });

            scrollBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

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

        if (notificationBell && notificationDropdown && notificationWrapper) {
            notificationBell.addEventListener('click', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                const isOpen = notificationDropdown.classList.contains('show');

                if (isOpen) {
                    notificationDropdown.classList.remove('show');
                    return;
                }

                notificationDropdown.classList.add('show');

                if (userDropdown) {
                    userDropdown.classList.remove('show');
                }

                if (userMenu) {
                    userMenu.classList.remove('open');
                }

                if (userMenuToggle) {
                    userMenuToggle.setAttribute('aria-expanded', 'false');
                }

                await loadNotifications({ silent: true });
            });

            notificationDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function (e) {
                if (!notificationWrapper.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }

        if (userMenu && userMenuToggle && userDropdown) {
            userMenuToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const isOpen = userDropdown.classList.contains('show');

                if (isOpen) {
                    userDropdown.classList.remove('show');
                    userMenu.classList.remove('open');
                    userMenuToggle.setAttribute('aria-expanded', 'false');
                } else {
                    userDropdown.classList.add('show');
                    userMenu.classList.add('open');
                    userMenuToggle.setAttribute('aria-expanded', 'true');

                    if (notificationDropdown) {
                        notificationDropdown.classList.remove('show');
                    }
                }
            });

            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function (e) {
                if (!userMenu.contains(e.target)) {
                    userDropdown.classList.remove('show');
                    userMenu.classList.remove('open');
                    userMenuToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (markAllNotificationsReadBtn) {
            markAllNotificationsReadBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                markAllNotificationsReadBtn.disabled = true;
                markAllNotificationsReadBtn.textContent = 'Please wait...';

                const success = await clearNotifications();

                if (success) {
                    updateNotificationCount(0);
                    previousNotificationIds = [];

                    if (notificationList) {
                        notificationList.innerHTML = `
                            <div class="notification-empty">
                                No notifications yet
                            </div>
                        `;
                    }
                }

                markAllNotificationsReadBtn.textContent = 'Clear all';
                markAllNotificationsReadBtn.disabled = true;
            });
        }

        loadNotifications({ silent: true });

        setInterval(() => {
            loadNotifications({ silent: false });
        }, {{ max(5000, $notificationRefreshInterval * 1000) }});

        lucide.createIcons();
    });
</script>

</body>
</html>