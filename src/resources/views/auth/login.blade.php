<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenSchool — Accedi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:        #F7F6F2;
            --surface:   #FFFFFF;
            --border:    #E4E2DA;
            --text:      #1A1916;
            --text-2:    #6B6860;
            --text-3:    #A8A69E;
            --accent:    #2A6B4A;
            --accent-bg: #EBF5EF;
            --accent-mid:#4A9B6F;
            --red:       #C0392B;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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

        nav {
            position: relative;
            z-index: 10;
            height: 60px;
            border-bottom: 1px solid var(--border);
            background: rgba(247,246,242,0.92);
            backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            padding: 0 2rem;
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
            box-shadow: 0 2px 8px rgba(42,107,74,0.22);
        }

        .logo-text {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.15rem;
            color: var(--text);
        }

        .page {
            flex: 1;
            display: flex;
            position: relative;
            z-index: 1;
        }

        /* LEFT PANEL */
        .left-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 4rem 4rem 3.5rem;
            background: var(--accent);
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 44px 44px;
        }

        /* decorative circle */
        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -140px;
            right: -140px;
            width: 440px; height: 440px;
            border: 70px solid rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        /* second decorative circle top-left */
        .left-deco {
            position: absolute;
            top: -80px; left: -80px;
            width: 280px; height: 280px;
            border: 50px solid rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }

        .left-top { position: relative; z-index: 1; }

        .left-tag {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 100px;
            padding: 5px 14px;
            font-size: 0.72rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            letter-spacing: 0.04em;
            margin-bottom: 1.75rem;
        }

        .left-tag-dot {
            width: 5px; height: 5px;
            background: #4ADE80;
            border-radius: 50%;
            animation: tagPulse 2s ease-in-out infinite;
        }

        @keyframes tagPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .left-headline {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: clamp(2.2rem, 3.8vw, 3.2rem);
            color: rgba(255,255,255,0.96);
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin-bottom: 1.25rem;
            max-width: 360px;
        }

        .left-headline em {
            font-style: italic;
            color: rgba(255,255,255,0.6);
        }

        .left-sub {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.65;
            max-width: 310px;
            font-weight: 300;
        }

        .left-bottom { position: relative; z-index: 1; }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.6);
            font-weight: 300;
        }

        .feature-icon {
            width: 30px; height: 30px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* RIGHT PANEL */
        .right-panel {
            width: 460px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem 3.5rem;
            background: var(--surface);
            border-left: 1px solid var(--border);
            animation: fadeSlide 0.45s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-eyebrow {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-3);
            margin-bottom: 10px;
        }

        .form-title {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 2.1rem;
            color: var(--text);
            letter-spacing: -0.03em;
            line-height: 1.05;
            margin-bottom: 8px;
        }

        .form-sub {
            font-size: 0.85rem;
            color: var(--text-3);
            margin-bottom: 2.5rem;
            font-weight: 300;
        }

        .field-group { margin-bottom: 1.2rem; }

        .field-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--text-2);
            margin-bottom: 7px;
            letter-spacing: 0.01em;
        }

        .field-input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.9rem;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }

        .field-input::placeholder { color: var(--text-3); }

        .field-input:focus {
            border-color: var(--accent-mid);
            box-shadow: 0 0 0 3px rgba(42,107,74,0.09);
            background: #fff;
        }

        .field-error {
            font-size: 0.74rem;
            color: var(--red);
            margin-top: 5px;
        }

        .submit-btn {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background 0.15s, box-shadow 0.15s, transform 0.1s;
            box-shadow: 0 2px 10px rgba(42,107,74,0.22);
            letter-spacing: 0.01em;
        }

        .submit-btn:hover {
            background: #1f5238;
            box-shadow: 0 4px 18px rgba(42,107,74,0.32);
        }

        .submit-btn:active { transform: scale(0.99); }

        .form-footer {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            font-size: 0.74rem;
            color: var(--text-3);
            text-align: center;
            font-weight: 300;
        }

        @media (max-width: 768px) {
            .left-panel { display: none; }
            .right-panel { width: 100%; border-left: none; padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>

<nav>
    <a href="/" class="logo">
        <div class="logo-mark">🌱</div>
        <span class="logo-text">GreenSchool</span>
    </a>
</nav>

<div class="page">

    <div class="left-panel">
        <div class="left-deco"></div>

        <div class="left-top">
            <div class="left-tag">
                <span class="left-tag-dot"></span>
                Sistema attivo
            </div>
            <h1 class="left-headline">Ricarica <em>intelligente</em> per le scuole.</h1>
            <p class="left-sub">La piattaforma che gestisce le colonnine di ricarica in tempo reale, ovunque tu sia.</p>
        </div>

        <div class="left-bottom">
            <ul class="feature-list">
                <li class="feature-item">
                    <span class="feature-icon">⚡</span>
                    Monitoraggio in tempo reale
                </li>
                <li class="feature-item">
                    <span class="feature-icon">📍</span>
                    Mappa interattiva delle stazioni
                </li>
                <li class="feature-item">
                    <span class="feature-icon">📊</span>
                    Reportistica consumi
                </li>
            </ul>
        </div>
    </div>

    <div class="right-panel">
        <p class="form-eyebrow">Portale studenti</p>
        <h2 class="form-title">Bentornato</h2>
        <p class="form-sub">Accedi al tuo account per gestire la ricarica.</p>

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

        <p class="form-footer">GreenSchool © {{ date('Y') }} — Portale di ricarica</p>
    </div>

</div>

</body>
</html>