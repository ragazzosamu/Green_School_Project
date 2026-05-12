<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenSchool — Portale Ricarica</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        }

        .gs-logo-mark {
            width: 28px;
            height: 28px;
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
            width: 1px;
            height: 16px;
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
        }
        .gs-nav-link:hover { background: var(--surface2); color: var(--text); }

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
        }
        .gs-logout-btn:hover { background: var(--red-bg); border-color: #EBCECA; }
    </style>
</head>
<body>

<nav class="gs-nav">
    <div class="gs-nav-inner">
        <a href="/" class="gs-logo">
            <div class="gs-logo-mark">🌱</div>
            <span class="gs-logo-text">GreenSchool</span>
        </a>
        <div class="gs-nav-right">
            @auth
                <span class="gs-nav-user">{{ Auth::user()->nome }}</span>
                <div class="gs-nav-divider"></div>
                <a href="/map" class="gs-nav-link">Mappa</a>
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="gs-logout-btn">Esci</button>
                </form>
            @else
                <a href="/login" class="gs-nav-link" style="color:var(--accent);font-weight:600;">Accedi</a>
            @endauth
        </div>
    </div>
</nav>

<main>
    @yield('content')
</main>

@stack('scripts')

</body>
</html>