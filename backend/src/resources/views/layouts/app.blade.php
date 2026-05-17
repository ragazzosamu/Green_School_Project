<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GreenSchool — Portale Ricarica</title>
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Font caricati UNA SOLA VOLTA qui nel layout --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:            #F7F6F2;
            --surface:       #FFFFFF;
            --surface2:      #F2F1ED;
            --border:        #E4E2DA;
            --border-strong: #CCCAC0;
            --text:          #1A1916;
            --text-2:        #6B6860;
            --text-3:        #A8A69E;
            --accent:        #2A6B4A;
            --accent-bg:     #EBF5EF;
            --accent-mid:    #4A9B6F;
            --red:           #C0392B;
            --red-bg:        #FDF0EE;
            --shadow-sm:     0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
            --shadow-lg:     0 12px 40px rgba(0,0,0,0.1), 0 2px 8px rgba(0,0,0,0.04);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(circle, #C8C6BE 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
        }

        main {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        /* ── NAVBAR ── */
        nav.gs-nav {
            position: sticky;
            top: 0;
            z-index: 200;
            background: rgba(247,246,242,0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }

        .gs-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .gs-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .gs-logo-mark {
            width: 28px; height: 28px;
            background: var(--accent);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(42,107,74,0.22);
        }

        .gs-logo-text {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.15rem;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        /* Desktop nav links */
        .gs-nav-right {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .gs-nav-user {
            font-size: 0.8rem;
            color: var(--text-3);
            padding: 0 10px;
            font-weight: 300;
        }

        .gs-nav-divider {
            width: 1px; height: 16px;
            background: var(--border);
            margin: 0 4px;
        }

        .gs-nav-link {
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-2);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .gs-nav-link:hover  { background: var(--surface2); color: var(--text); }
        .gs-nav-link.active { background: var(--accent-bg); color: var(--accent); }

        .gs-logout-btn {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--red);
            background: none;
            border: 1px solid transparent;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .gs-logout-btn:hover { background: var(--red-bg); border-color: #EBCECA; }

        /* ── HAMBURGER (solo mobile) ── */
        .gs-hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: background 0.15s;
        }
        .gs-hamburger:hover { background: var(--surface2); }
        .gs-hamburger span {
            display: block;
            width: 22px; height: 2px;
            background: var(--text);
            border-radius: 2px;
            transition: all 0.2s;
        }

        /* Drawer mobile */
        .gs-mobile-menu {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 199;
        }
        .gs-mobile-menu.open { display: block; }

        .gs-mobile-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.25);
        }

        .gs-mobile-drawer {
            position: absolute;
            top: 60px; right: 0;
            width: 220px;
            background: var(--surface);
            border-left: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            border-radius: 0 0 0 16px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: var(--shadow-lg);
            animation: drawerIn 0.2s ease;
        }
        @keyframes drawerIn {
            from { opacity: 0; transform: translateX(12px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .gs-mobile-drawer .gs-nav-link {
            display: block;
            padding: 10px 14px;
            font-size: 0.9rem;
        }
        .gs-mobile-drawer .gs-nav-divider {
            width: 100%; height: 1px;
            margin: 4px 0;
        }
        .gs-mobile-drawer .gs-logout-btn {
            width: 100%;
            text-align: left;
            padding: 10px 14px;
            font-size: 0.9rem;
        }
        .gs-mobile-user {
            font-size: 0.78rem;
            color: var(--text-3);
            padding: 6px 14px 10px;
            font-weight: 300;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .gs-nav-right  { display: none; }
            .gs-hamburger  { display: flex; }

            main { padding: 1.5rem 1rem; }
        }
    </style>
</head>
<body>

<nav class="gs-nav">
    <div class="gs-nav-inner">
        <a href="/" class="gs-logo">
            <div class="gs-logo-mark">🌱</div>
            <span class="gs-logo-text">GreenSchool</span>
        </a>

        {{-- Desktop --}}
        <div class="gs-nav-right">
            @auth
                <span class="gs-nav-user">{{ Auth::user()->nome }}</span>
                <div class="gs-nav-divider"></div>
                <a href="/map"        class="gs-nav-link {{ request()->is('map')        ? 'active' : '' }}">Mappa</a>
                <a href="/profilo"    class="gs-nav-link {{ request()->is('profilo')    ? 'active' : '' }}">Profilo</a>
                <a href="/classifica" class="gs-nav-link {{ request()->is('classifica') ? 'active' : '' }}">Classifica</a>
                <a href="/scuola"     class="gs-nav-link {{ request()->is('scuola')     ? 'active' : '' }}">Scuola</a>
                <div class="gs-nav-divider"></div>
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="gs-logout-btn">Esci</button>
                </form>
            @else
                <a href="/login" class="gs-nav-link" style="color:var(--accent);font-weight:600;">Accedi</a>
            @endauth
        </div>

        {{-- Mobile hamburger --}}
        @auth
        <button class="gs-hamburger" id="gs-hamburger" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        @endauth
    </div>
</nav>

{{-- Mobile drawer --}}
@auth
<div class="gs-mobile-menu" id="gs-mobile-menu">
    <div class="gs-mobile-overlay" id="gs-mobile-overlay"></div>
    <div class="gs-mobile-drawer">
        <p class="gs-mobile-user">{{ Auth::user()->nome }}</p>
        <a href="/map"        class="gs-nav-link {{ request()->is('map')        ? 'active' : '' }}">Mappa</a>
        <a href="/profilo"    class="gs-nav-link {{ request()->is('profilo')    ? 'active' : '' }}">Profilo</a>
        <a href="/classifica" class="gs-nav-link {{ request()->is('classifica') ? 'active' : '' }}">Classifica</a>
        <a href="/scuola"     class="gs-nav-link {{ request()->is('scuola')     ? 'active' : '' }}">Scuola</a>
        <div class="gs-nav-divider"></div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="gs-logout-btn">Esci</button>
        </form>
    </div>
</div>

<script>
    const hamburger  = document.getElementById('gs-hamburger');
    const mobileMenu = document.getElementById('gs-mobile-menu');
    const overlay    = document.getElementById('gs-mobile-overlay');

    hamburger?.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.toggle('open');
        hamburger.setAttribute('aria-expanded', isOpen);
    });
    overlay?.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        hamburger.setAttribute('aria-expanded', false);
    });
</script>
@endauth

<main>
    @yield('content')
</main>

@stack('scripts')

</body>
</html>