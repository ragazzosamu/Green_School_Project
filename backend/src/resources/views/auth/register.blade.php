<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenSchool — Registrati</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;1,700&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            padding: 2.5rem 1.25rem 3rem;
        }

        /* ── HEADLINE ── */
        .headline-wrap {
            text-align: center;
            margin-bottom: 1.75rem;
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
            margin-bottom: 1.1rem;
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
            font-size: clamp(2.2rem, 5vw, 3.2rem);
            color: #FFFFFF;
            letter-spacing: -0.045em;
            line-height: 1.02;
            margin-bottom: 0.75rem;
        }

        .main-headline em {
            font-style: italic;
            color: rgba(255,255,255,0.42);
        }

        .main-sub {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.32);
            max-width: 310px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* ── REGISTER CARD ── */
        .register-card {
            width: 100%;
            max-width: 440px;
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
            padding: 2rem 2.25rem 1.75rem;
        }

        .card-title {
            font-family: 'Instrument Serif', Georgia, serif;
            font-size: 1.45rem;
            color: var(--text);
            letter-spacing: -0.03em;
            margin-bottom: 3px;
        }

        .card-sub {
            font-size: 0.8rem;
            color: var(--text-3);
            margin-bottom: 1.5rem;
        }

        /* Form fields */
        .fields-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0.85rem;
        }

        .field-group { margin-bottom: 0.85rem; }
        .field-group:last-of-type { margin-bottom: 0; }

        .field-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-2);
            margin-bottom: 5px;
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

        /* Selezione tipo account */
        .tipo-select {
            width: 100%;
            background: #F7F6F2;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.9rem;
            color: var(--text);
            font-family: 'Geist', sans-serif;
            outline: none;
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23A8A69E' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .tipo-select:focus {
            border-color: var(--accent-mid);
            box-shadow: 0 0 0 3px rgba(42,107,74,0.1);
            background-color: #fff;
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
            margin-top: 1rem;
            letter-spacing: 0.01em;
            transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
            box-shadow: 0 2px 12px rgba(42,107,74,0.3);
        }

        .submit-btn:hover {
            background: var(--accent-dark);
            box-shadow: 0 4px 20px rgba(42,107,74,0.42);
        }

        .submit-btn:active { transform: scale(0.99); }

        /* Link accedi */
        .card-footer-link {
            border-top: 1px solid var(--border);
            padding: 14px 2.25rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-3);
        }

        .card-footer-link a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
        }

        .card-footer-link a:hover { text-decoration: underline; }

        /* Alert errori generali */
        .alert-error {
            background: #FDF0EE;
            border: 1px solid #EBCECA;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: var(--red);
            margin-bottom: 1rem;
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
                Crea il tuo account
            </div>
            <h1 class="main-headline">Benvenuto<em>.</em></h1>
            <p class="main-sub">Registrati per accedere alle colonnine di ricarica del tuo istituto.</p>
        </div>

        <div class="register-card">
            <div class="card-strip"></div>
            <div class="card-body">
                <h2 class="card-title">Registrati</h2>
                <p class="card-sub">Compila i dati per creare il tuo account</p>

                @if ($errors->any())
                    <div class="alert-error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <div class="fields-row">
                        <div class="field-group" style="margin-bottom:0;">
                            <label class="field-label" for="nome">Nome</label>
                            <input class="field-input" id="nome" type="text" name="nome"
                                   value="{{ old('nome') }}" required autocomplete="given-name"
                                   placeholder="Mario">
                            @error('nome')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field-group" style="margin-bottom:0;">
                            <label class="field-label" for="cognome">Cognome</label>
                            <input class="field-input" id="cognome" type="text" name="cognome"
                                   value="{{ old('cognome') }}" required autocomplete="family-name"
                                   placeholder="Rossi">
                            @error('cognome')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="email">Indirizzo email</label>
                        <input class="field-input" id="email" type="email" name="email"
                               value="{{ old('email') }}" required autocomplete="email"
                               placeholder="nome@scuola.it">
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="cellulare">Cellulare <span style="color:var(--text-3); font-weight:400;">(opzionale)</span></label>
                        <input class="field-input" id="cellulare" type="tel" name="cellulare"
                               value="{{ old('cellulare') }}" autocomplete="tel"
                               placeholder="+39 333 1234567">
                        @error('cellulare')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    

                    <div class="field-group">
                        <label class="field-label" for="password">Password</label>
                        <input class="field-input" id="password" type="password" name="password"
                               required autocomplete="new-password"
                               placeholder="Minimo 8 caratteri">
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="password_confirmation">Conferma password</label>
                        <input class="field-input" id="password_confirmation" type="password"
                               name="password_confirmation" required autocomplete="new-password"
                               placeholder="Ripeti la password">
                    </div>

                    <button type="submit" class="submit-btn">Crea account →</button>
                </form>
            </div>

            <div class="card-footer-link">
                Hai già un account? <a href="{{ route('login') }}">Accedi</a>
            </div>
        </div>

    </div>

    <footer>
        GreenSchool © {{ date('Y') }} — Portale di ricarica scolastica
    </footer>

</body>
</html>