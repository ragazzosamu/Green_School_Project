@extends('layouts.app')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap');

    :root {
        --bg:        #F7F6F2;
        --surface:   #FFFFFF;
        --surface2:  #F2F1ED;
        --border:    #E4E2DA;
        --text:      #1A1916;
        --text-2:    #6B6860;
        --text-3:    #A8A69E;
        --accent:    #2A6B4A;
        --accent-bg: #EBF5EF;
        --accent-mid:#4A9B6F;
        --gold:      #D4A017;
        --gold-bg:   #FDF8EC;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
    }

    /* ── PAGE HEADER ── */
    .page-header {
        margin-bottom: 2rem;
    }

    .page-eyebrow {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 5px;
    }

    .page-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.75rem;
        color: var(--text);
        letter-spacing: -0.03em;
        line-height: 1;
    }

    /* ── LAYOUT GRIGLIA ── */
    .profile-grid {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 900px) {
        .profile-grid { grid-template-columns: 1fr; }
    }

    /* ── CARD BASE ── */
    .gs-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    /* ── HERO CARD (colonna sinistra) ── */
    .hero-card-top {
        background: linear-gradient(160deg, #1A3D2A 0%, #2A6B4A 100%);
        padding: 2rem 1.75rem 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .hero-card-top::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 180px; height: 180px;
        border-radius: 50%;
        background: rgba(255,255,255,0.04);
    }

    .hero-card-top::after {
        content: '';
        position: absolute;
        bottom: -20px; left: -20px;
        width: 120px; height: 120px;
        border-radius: 50%;
        background: rgba(255,255,255,0.03);
    }

    .hero-avatar {
        width: 56px; height: 56px;
        border-radius: 14px;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 1rem;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .hero-name {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.4rem;
        color: #fff;
        letter-spacing: -0.02em;
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .hero-role {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        font-weight: 400;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .hero-level-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .hero-level-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.5);
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .hero-level-val {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1rem;
        color: #7EEAAA;
    }

    .xp-track {
        height: 6px;
        background: rgba(255,255,255,0.12);
        border-radius: 100px;
        overflow: hidden;
    }

    .xp-bar {
        height: 100%;
        border-radius: 100px;
        background: linear-gradient(90deg, #7EEAAA, #4ADE80);
        transition: width 1s cubic-bezier(0.25,1,0.5,1);
    }

    .xp-sub {
        font-size: 0.68rem;
        color: rgba(255,255,255,0.35);
        margin-top: 5px;
        text-align: right;
    }

    /* Stats sotto l'hero */
    .hero-card-bottom {
        padding: 1.25rem 1.75rem;
    }

    .stats-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .stat-item {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.9rem 1rem;
    }

    .stat-item-label {
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 4px;
    }

    .stat-item-val {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.6rem;
        color: var(--text);
        line-height: 1;
        letter-spacing: -0.02em;
    }

    .stat-item-val.green { color: var(--accent); }
    .stat-item-val.gold  { color: var(--gold); }

    .stat-item-unit {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.75rem;
        color: var(--text-3);
        font-weight: 400;
        margin-left: 2px;
    }

    /* ── SEZIONE BADGE ── */
    .section-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-3);
        padding: 1.1rem 1.5rem 0.6rem;
        border-top: 1px solid var(--border);
    }

    .section-label:first-child { border-top: none; }

    .badge-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
        padding: 0 1.5rem 1.5rem;
    }

    .badge-item {
        border-radius: 12px;
        border: 1px solid var(--border);
        padding: 0.85rem 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: box-shadow 0.15s;
    }

    .badge-item:hover { box-shadow: var(--shadow-sm); }

    .badge-item.unlocked {
        background: var(--gold-bg);
        border-color: #E8D5A0;
    }

    .badge-item.locked {
        background: var(--surface2);
        opacity: 0.55;
        filter: grayscale(0.4);
    }

    .badge-emoji {
        font-size: 1.5rem;
        flex-shrink: 0;
        line-height: 1;
    }

    .badge-emoji.locked-icon {
        font-size: 1.1rem;
        opacity: 0.4;
    }

    .badge-name {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text);
        line-height: 1.3;
    }

    .badge-desc {
        font-size: 0.65rem;
        color: var(--text-3);
        margin-top: 1px;
    }

    .badge-date {
        font-size: 0.6rem;
        color: var(--gold);
        font-weight: 500;
        margin-top: 2px;
    }

    /* ── COLONNA DESTRA ── */
    .right-col {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Streak card */
    .streak-card-inner {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .streak-circle {
        width: 72px; height: 72px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FFF3CC, #FFE082);
        border: 2px solid #E8D5A0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .streak-num {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.6rem;
        color: #8B6914;
        line-height: 1;
    }

    .streak-label {
        font-size: 0.55rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #A07820;
        margin-top: 1px;
    }

    .streak-info-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.1rem;
        color: var(--text);
        margin-bottom: 3px;
    }

    .streak-info-sub {
        font-size: 0.78rem;
        color: var(--text-3);
        line-height: 1.5;
    }

    /* XP totali grande */
    .xp-hero-inner {
        padding: 1.75rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .xp-hero-left {}

    .xp-hero-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 6px;
    }

    .xp-hero-number {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 3.5rem;
        color: var(--accent);
        letter-spacing: -0.04em;
        line-height: 1;
    }

    .xp-hero-unit {
        font-size: 1rem;
        color: var(--text-3);
        font-weight: 300;
        margin-left: 4px;
    }

    .xp-hero-sub {
        font-size: 0.78rem;
        color: var(--text-3);
        margin-top: 6px;
    }

    .xp-hero-right {
        text-align: right;
    }

    .level-badge {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        background: var(--accent-bg);
        border: 1px solid #C5E0D0;
        border-radius: 14px;
        padding: 12px 18px;
        min-width: 80px;
    }

    .level-badge-num {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 2rem;
        color: var(--accent);
        line-height: 1;
    }

    .level-badge-label {
        font-size: 0.6rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--accent-mid);
        margin-top: 3px;
    }

    /* Mock notice */
    .mock-notice {
        background: #FFF8E7;
        border: 1px solid #F0DFA0;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.75rem;
        color: #8B6914;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="page-header">
    <p class="page-eyebrow">Il tuo profilo</p>
    <h1 class="page-title">Gamification</h1>
</div>

{{-- Avviso dati mock — da rimuovere quando il backend è pronto --}}
<div class="mock-notice" style="margin-bottom:1.5rem;">
    ⚠️ I dati mostrati sono di esempio. Il backend (3.4–3.6) non è ancora collegato.
</div>

<div class="profile-grid">

    {{-- ── COLONNA SINISTRA ── --}}
    <div class="gs-card">

        {{-- Hero --}}
        <div class="hero-card-top">
            <div class="hero-avatar">🌿</div>
            <p class="hero-name">{{ Auth::user()->nome }} {{ Auth::user()->cognome }}</p>
            <p class="hero-role">{{ Auth::user()->tipo_account ?? 'Studente' }}</p>

            <div class="hero-level-row">
                <span class="hero-level-label">Livello 4 → 5</span>
                <span class="hero-level-val">720 / 1000 XP</span>
            </div>
            <div class="xp-track">
                <div class="xp-bar" style="width: 72%"></div>
            </div>
            <p class="xp-sub">280 XP al prossimo livello</p>
        </div>

        {{-- Stats --}}
        <div class="hero-card-bottom">
            <div class="stats-row">
                <div class="stat-item">
                    <p class="stat-item-label">CO₂ risparmiata</p>
                    <p class="stat-item-val green">12<span class="stat-item-unit">kg</span></p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">Sessioni totali</p>
                    <p class="stat-item-val">8</p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">kWh erogati</p>
                    <p class="stat-item-val green">47<span class="stat-item-unit">kWh</span></p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">Streak</p>
                    <p class="stat-item-val gold">5<span class="stat-item-unit">gg</span></p>
                </div>
            </div>
        </div>

        {{-- Badge sbloccati --}}
        <p class="section-label">Badge sbloccati</p>
        <div class="badge-grid">
            <div class="badge-item unlocked">
                <span class="badge-emoji">⚡</span>
                <div>
                    <p class="badge-name">Prima Ricarica</p>
                    <p class="badge-desc">Hai completato la tua prima sessione</p>
                    <p class="badge-date">12 mag 2026</p>
                </div>
            </div>
            <div class="badge-item unlocked">
                <span class="badge-emoji">🌙</span>
                <div>
                    <p class="badge-name">Notturno Green</p>
                    <p class="badge-desc">Ricarica completata dopo le 20:00</p>
                    <p class="badge-date">13 mag 2026</p>
                </div>
            </div>
        </div>

        {{-- Badge da sbloccare --}}
        <p class="section-label">Da sbloccare</p>
        <div class="badge-grid" style="padding-bottom:1.5rem;">
            <div class="badge-item locked">
                <span class="badge-emoji locked-icon">🏆</span>
                <div>
                    <p class="badge-name">Eco Champion</p>
                    <p class="badge-desc">Risparmia 50 kg di CO₂</p>
                </div>
            </div>
            <div class="badge-item locked">
                <span class="badge-emoji locked-icon">🔥</span>
                <div>
                    <p class="badge-name">Streak 7</p>
                    <p class="badge-desc">7 giorni consecutivi</p>
                </div>
            </div>
        </div>

    </div>

    {{-- ── COLONNA DESTRA ── --}}
    <div class="right-col">

        {{-- XP totali --}}
        <div class="gs-card">
            <div class="xp-hero-inner">
                <div class="xp-hero-left">
                    <p class="xp-hero-label">Punti esperienza totali</p>
                    <p class="xp-hero-number">720<span class="xp-hero-unit">XP</span></p>
                    <p class="xp-hero-sub">Guadagni XP completando sessioni di ricarica</p>
                </div>
                <div class="xp-hero-right">
                    <div class="level-badge">
                        <span class="level-badge-num">4</span>
                        <span class="level-badge-label">Livello</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Streak --}}
        <div class="gs-card">
            <div class="streak-card-inner">
                <div class="streak-circle">
                    <span class="streak-num">5</span>
                    <span class="streak-label">giorni</span>
                </div>
                <div>
                    <p class="streak-info-title">Streak attiva 🔥</p>
                    <p class="streak-info-sub">Hai ricaricato per 5 giorni consecutivi.<br>Continua così per sbloccare il badge <strong>Streak 7</strong>!</p>
                </div>
            </div>
        </div>

        {{-- Ultime sessioni --}}
        <div class="gs-card">
            <p class="section-label" style="border-top:none; padding-top:1.1rem;">Ultime sessioni</p>
            <div style="padding: 0 1.5rem 1.5rem; display:flex; flex-direction:column; gap:0.6rem;">

                @php
                $sessioni_mock = [
                    ['data' => '14 mag 2026', 'kwh' => '6.2', 'xp' => '+50 XP', 'durata' => '1h 12m', 'costo' => '€ 1.24'],
                    ['data' => '13 mag 2026', 'kwh' => '8.5', 'xp' => '+50 XP', 'durata' => '1h 42m', 'costo' => '€ 1.70'],
                    ['data' => '12 mag 2026', 'kwh' => '4.1', 'xp' => '+100 XP', 'durata' => '0h 49m', 'costo' => '€ 0.82'],
                ];
                @endphp

                @foreach($sessioni_mock as $s)
                <div style="
                    background: var(--surface2);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 0.9rem 1.1rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.75rem;
                ">
                    <div>
                        <p style="font-size:0.8rem; font-weight:600; color:var(--text);">{{ $s['data'] }}</p>
                        <p style="font-size:0.72rem; color:var(--text-3);">{{ $s['durata'] }} · {{ $s['kwh'] }} kWh · {{ $s['costo'] }}</p>
                    </div>
                    <span style="
                        font-size: 0.7rem;
                        font-weight: 700;
                        color: var(--accent);
                        background: var(--accent-bg);
                        border: 1px solid #C5E0D0;
                        border-radius: 100px;
                        padding: 3px 10px;
                        white-space: nowrap;
                    ">{{ $s['xp'] }}</span>
                </div>
                @endforeach

                <a href="/storico" style="
                    display: block;
                    text-align: center;
                    font-size: 0.78rem;
                    font-weight: 500;
                    color: var(--text-3);
                    text-decoration: none;
                    margin-top: 4px;
                    padding: 6px;
                    border-radius: 8px;
                    transition: color 0.15s;
                " onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-3)'">
                    Vedi tutto lo storico →
                </a>
            </div>
        </div>

    </div>
</div>

@endsection