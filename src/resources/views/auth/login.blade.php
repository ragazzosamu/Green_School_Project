<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenSchool — Accedi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
   <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <!-- <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">-->
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:      #2A6B4A;
            --accent-mid:  #3D8A61;
            --accent-dark: #1f5238;
            --text:        #1A1916;
            --text-2:      #6B6860;
            --text-3:      #A8A69E;
            --border:      #E4E2DA;
            --red:         #C0392B;
        }

        html, body { height: 100%; }

        body {
            font-family: 'Geist', sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #0F1A14;
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── BACKGROUND ── */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        .bg-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 55% at 50% -5%, rgba(42,107,74,0.6) 0%, transparent 65%),
                radial-gradient(ellipse 60% 40% at 15% 90%, rgba(42,107,74,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 35% at 85% 85%, rgba(20,60,35,0.2) 0%, transparent 60%),
                #0F1A14;
        }

        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 52px 52px;
        }

        .bg-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.055) 1px, transparent 1px);
            background-size: 26px 26px;
        }

        /* Anelli concentrici centrati in alto */
        .bg-rings {
            position: absolute;
            top: -320px;
            left: 50%;
            transform: translateX(-50%);
        }

        .ring {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(42,107,74,0.18);
            transform: translate(-50%, 0);
            left: 50%;
        }

        .ring-1 { width: 800px; height: 800px; top: 0; }
        .ring-2 { width: 580px; height: 580px; top: 110px; border-color: rgba(42,107,74,0.13); }
        .ring-3 { width: 380px; height: 380px; top: 210px; border-color: rgba(42,107,74,0.09); }

        /* ── NAV ── */
        nav {
            position: relative;
            z-index: 10;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-mark {
            width: 28px; height: 28px;
            background: var(--accent);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            box-shadow: 0 2px 14px rgba(42,107,74,0.5);
        }

        .logo-text {
            font-family: 'Instrument Serif', Georgia, serif;
            font-size: 1.15rem;
            color: rgba(255,255,255,0.88);
        }

        .nav-label {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.25);
            letter-spacing: 0.05em;
        }

        /* ── CENTRO PAGINA ── */
        .page-center {
            flex: 1;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.25rem 4rem;
            gap: 0;
        }

        /* ── HEADLINE ── */
        .headline-wrap {
            text-align: center;
            margin-bottom: 2.25rem;
            animation: riseUp 0.55s cubic-bezier(0.16,1,0.3,1) both;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(42,107,74,0.18);
            border: 1px solid rgba(42,107,74,0.38);
            border-radius: 100px;
            padding: 5px 14px 5px 10px;
            font-size: 0.72rem;
            font-weight: 500;
            color: #7EEAAA;
            letter-spacing: 0.05em;
            margin-bottom: 1.3rem;
        }

        .pill-dot {
            width: 6px; height: 6px;
            background: #7EEAAA;
            border-radius: 50%;
            box-shadow: 0 0 8px #7EEAAA;
            animation: pillBlink 2s ease-in-out infinite;
        }

        @keyframes pillBlink {
            0%,100% { opacity:1; }
            50%      { opacity:0.35; }
        }

        .main-headline {
            font-family: 'Instrument Serif', Georgia, serif;
            font-size: clamp(2.6rem, 6vw, 4rem);
            color: #FFFFFF;
            letter-spacing: -0.045em;
            line-height: 1.02;
            margin-bottom: 0.9rem;
        }

        .main-headline em {
            font-style: italic;
            color: rgba(255,255,255,0.42);
        }

        .main-sub {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.32);
            max-width: 310px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* ── LOGIN CARD ── */
        .login-card {
            width: 100%;
            max-width: 410px;
            background: #FFFFFF;
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(0,0,0,0.08),
                0 32px 80px rgba(0,0,0,0.4),
                0 6px 20px rgba(0,0,0,0.18);
            animation: riseUp 0.55s 0.08s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes riseUp {
            from { opacity:0; transform: translateY(22px); }
            to   { opacity:1; transform: translateY(0); }
        }

        /* Strip verde in cima */
        .card-strip {
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #5ABF85, var(--accent));
            background-size: 200% 100%;
            animation: stripShift 3s ease-in-out infinite;
        }

        @keyframes stripShift {
            0%,100% { background-position: 0% 0%; }
            50%      { background-position: 100% 0%; }
        }

        .card-body {
            padding: 2.25rem 2.25rem 2rem;
        }

        .card-title {
            font-family: 'Instrument Serif', Georgia, serif;
            font-size: 1.55rem;
            color: var(--text);
            letter-spacing: -0.03em;
            margin-bottom: 3px;
        }

        .card-sub {
            font-size: 0.8rem;
            color: var(--text-3);
            margin-bottom: 1.75rem;
        }

        /* Form fields */
        .field-group { margin-bottom: 1rem; }

        .field-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .field-input {
            width: 100%;
            background: #F7F6F2;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.9rem;
            color: var(--text);
            font-family: 'Geist', sans-serif;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }

        .field-input::placeholder { color: var(--text-3); }

        .field-input:focus {
            border-color: var(--accent-mid);
            box-shadow: 0 0 0 3px rgba(42,107,74,0.1);
            background: #fff;
        }

        .field-error {
            font-size: 0.72rem;
            color: var(--red);
            margin-top: 4px;
        }

        .submit-btn {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-family: 'Geist', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.6rem;
            letter-spacing: 0.01em;
            transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
            box-shadow: 0 2px 12px rgba(42,107,74,0.3);
        }

        .submit-btn:hover {
            background: var(--accent-dark);
            box-shadow: 0 4px 20px rgba(42,107,74,0.42);
        }

        .submit-btn:active { transform: scale(0.99); }

        /* Feature strip in fondo alla card */
        .card-features {
            display: flex;
            border-top: 1px solid var(--border);
        }

        .cf-item {
            flex: 1;
            padding: 14px 8px;
            text-align: center;
            border-right: 1px solid var(--border);
        }

        .cf-item:last-child { border-right: none; }

        .cf-icon { font-size: 1rem; margin-bottom: 3px; }

        .cf-label {
            font-size: 0.64rem;
            color: var(--text-3);
            line-height: 1.35;
        }

        /* ── FOOTER ── */
        footer {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 1.1rem;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.18);
            border-top: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    <div class="bg-layer">
        <div class="bg-gradient"></div>
        <div class="bg-grid"></div>
        <div class="bg-dots"></div>
        <div class="bg-rings">
            <div class="ring ring-1"></div>
            <div class="ring ring-2"></div>
            <div class="ring ring-3"></div>
        </div>
    </div>

    <nav>
        <a href="/" class="logo">
            <div class="logo-mark">🌱</div>
            <span class="logo-text">GreenSchool</span>
        </a>
        <span class="nav-label">Portale studenti</span>
    </nav>

    <div class="page-center">

        <div class="headline-wrap">
            <div class="status-pill">
                <span class="pill-dot"></span>
                Ricarica intelligente
            </div>
            <h1 class="main-headline">Bentornato<em>.</em></h1>
            <p class="main-sub">Accedi per gestire le colonnine di ricarica del tuo istituto in tempo reale.</p>
        </div>

        <div class="login-card">
            <div class="card-strip"></div>
            <div class="card-body">
                <h2 class="card-title">Accedi</h2>
                <p class="card-sub">Inserisci le credenziali del tuo account</p>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="field-group">
                        <label class="field-label" for="email">Indirizzo email</label>
                        <input class="field-input" id="email" type="email" name="email"
                               value="{{ old('email') }}" required autocomplete="email" autofocus
                               placeholder="nome@scuola.it">
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="password">Password</label>
                        <input class="field-input" id="password" type="password" name="password"
                               required autocomplete="current-password"
                               placeholder="••••••••">
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="submit-btn">Accedi al portale →</button>
                </form>
            </div>

            <div class="card-features">
                <div class="cf-item">
                    <div class="cf-icon">⚡</div>
                    <div class="cf-label">Tempo reale</div>
                </div>
                <div class="cf-item">
                    <div class="cf-icon">📍</div>
                    <div class="cf-label">Mappa live</div>
                </div>
                <div class="cf-item">
                    <div class="cf-icon">📊</div>
                    <div class="cf-label">Storico</div>
                </div>
            </div>
        </div>

    </div>

    <footer>
        GreenSchool © {{ date('Y') }} — Portale di ricarica scolastica
    </footer>

</body>
</html>