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
        --gold-border:#E8D5A0;
        --silver:    #8A9BB0;
        --silver-bg: #F4F6F8;
        --bronze:    #B87333;
        --bronze-bg: #FBF4EE;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
    }

    /* ── PAGE HEADER ── */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 1.75rem;
        gap: 1rem;
        flex-wrap: wrap;
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

    /* ── REFRESH BUTTON ── */
    .refresh-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        padding: 8px 16px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.76rem;
        font-weight: 500;
        color: var(--text-2);
        cursor: pointer;
        transition: all 0.15s;
        box-shadow: var(--shadow-sm);
    }

    .refresh-btn:hover {
        border-color: var(--accent-mid);
        color: var(--accent);
        background: var(--accent-bg);
    }

    .refresh-btn.spinning svg {
        animation: spin360 0.7s linear infinite;
    }

    @keyframes spin360 {
        to { transform: rotate(360deg); }
    }

    /* ── LAYOUT ── */
    .leaderboard-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 900px) {
        .leaderboard-layout { grid-template-columns: 1fr; }
        .sidebar-col { order: -1; }
    }

    /* ── MAIN CARD ── */
    .gs-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    /* ── PODIO (top 3) ── */
    .podio-section {
        background: linear-gradient(160deg, #1A3D2A 0%, #2A6B4A 100%);
        padding: 2rem 2rem 2.5rem;
        position: relative;
        overflow: hidden;
    }

    .podio-section::before {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 240px; height: 240px;
        border-radius: 50%;
        background: rgba(255,255,255,0.04);
        pointer-events: none;
    }

    .podio-section::after {
        content: '';
        position: absolute;
        bottom: -30px; left: -30px;
        width: 160px; height: 160px;
        border-radius: 50%;
        background: rgba(255,255,255,0.03);
        pointer-events: none;
    }

    .podio-label {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.4);
        margin-bottom: 1.5rem;
        position: relative;
        z-index: 1;
    }

    .podio-row {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 1rem;
        position: relative;
        z-index: 1;
    }

    .podio-slot {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        flex: 1;
        max-width: 140px;
        animation: podioIn 0.5s cubic-bezier(0.16,1,0.3,1) both;
    }

    .podio-slot:nth-child(1) { animation-delay: 0.1s; }
    .podio-slot:nth-child(2) { animation-delay: 0s; margin-bottom: 1rem; }
    .podio-slot:nth-child(3) { animation-delay: 0.2s; }

    @keyframes podioIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .podio-avatar {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.25rem;
        color: #fff;
        font-weight: 400;
        letter-spacing: -0.02em;
        border: 2px solid rgba(255,255,255,0.2);
        position: relative;
        flex-shrink: 0;
    }

    .podio-avatar.pos-1 {
        width: 60px; height: 60px;
        font-size: 1.5rem;
        background: rgba(212,160,23,0.25);
        border-color: rgba(212,160,23,0.6);
        box-shadow: 0 0 0 3px rgba(212,160,23,0.15);
    }

    .podio-avatar.pos-2 {
        background: rgba(138,155,176,0.2);
        border-color: rgba(138,155,176,0.5);
    }

    .podio-avatar.pos-3 {
        background: rgba(184,115,51,0.2);
        border-color: rgba(184,115,51,0.5);
    }

    .podio-crown {
        position: absolute;
        top: -14px;
        font-size: 16px;
        line-height: 1;
    }

    .podio-name {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.78rem;
        font-weight: 600;
        color: rgba(255,255,255,0.9);
        text-align: center;
        max-width: 120px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .podio-xp {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1rem;
        line-height: 1;
    }

    .podio-xp.pos-1 { color: #FBBF24; }
    .podio-xp.pos-2 { color: rgba(255,255,255,0.55); }
    .podio-xp.pos-3 { color: #C4956A; }

    .podio-rank {
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 2px 8px;
        border-radius: 100px;
    }

    .podio-rank.pos-1 { background: rgba(212,160,23,0.25); color: #FBBF24; }
    .podio-rank.pos-2 { background: rgba(138,155,176,0.15); color: rgba(255,255,255,0.5); }
    .podio-rank.pos-3 { background: rgba(184,115,51,0.2); color: #C4956A; }

    /* Placeholder podio slot (quando < 3 utenti) */
    .podio-slot.empty .podio-avatar {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.1);
        border-style: dashed;
    }

    .podio-slot.empty .podio-name {
        color: rgba(255,255,255,0.2);
    }

    /* ── LISTA CLASSIFICA (dal 4° posto) ── */
    .rank-list {
        padding: 0.75rem 0;
    }

    .rank-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.9rem 1.75rem;
        border-bottom: 1px solid var(--border);
        transition: background 0.12s;
        animation: rankIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
    }

    .rank-item:last-child { border-bottom: none; }
    .rank-item:hover { background: var(--surface2); }

    @keyframes rankIn {
        from { opacity: 0; transform: translateX(-8px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .rank-pos {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.1rem;
        color: var(--text-3);
        width: 28px;
        text-align: center;
        flex-shrink: 0;
        letter-spacing: -0.02em;
    }

    .rank-avatar {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: var(--accent-bg);
        border: 1px solid #C5E0D0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 0.95rem;
        color: var(--accent);
        flex-shrink: 0;
    }

    /* Evidenzia l'utente corrente */
    .rank-item.is-me {
        background: var(--accent-bg);
        border-color: #C5E0D0;
    }

    .rank-item.is-me .rank-pos { color: var(--accent); }
    .rank-item.is-me .rank-avatar {
        background: var(--accent);
        border-color: #1f5238;
        color: #fff;
    }

    .rank-info { flex: 1; min-width: 0; }

    .rank-name {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rank-meta {
        font-size: 0.72rem;
        color: var(--text-3);
        margin-top: 1px;
    }

    .rank-you-badge {
        font-size: 0.6rem;
        font-weight: 700;
        background: var(--accent);
        color: #fff;
        padding: 2px 7px;
        border-radius: 100px;
        margin-left: 7px;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .rank-xp {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.3rem;
        color: var(--text);
        letter-spacing: -0.02em;
        flex-shrink: 0;
    }

    .rank-xp-unit {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.65rem;
        color: var(--text-3);
        font-weight: 400;
    }

    /* ── SIDEBAR ── */
    .sidebar-col {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Posizione personale */
    .my-rank-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    .my-rank-top {
        background: linear-gradient(160deg, #1A3D2A 0%, #2A6B4A 100%);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .my-rank-circle {
        width: 56px; height: 56px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.25);
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        flex-shrink: 0;
    }

    .my-rank-num {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.4rem;
        color: #fff;
        line-height: 1;
    }

    .my-rank-of {
        font-size: 0.55rem;
        color: rgba(255,255,255,0.45);
        font-weight: 500;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .my-rank-info {}

    .my-rank-label {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.45);
        margin-bottom: 3px;
    }

    .my-rank-name {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.1rem;
        color: #fff;
        letter-spacing: -0.02em;
    }

    .my-rank-xp {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.5);
        margin-top: 2px;
    }

    .my-rank-bottom {
        padding: 1.25rem 1.5rem;
    }

    .my-rank-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
    }

    .my-rank-stat:last-child { border-bottom: none; }

    .my-rank-stat-label { color: var(--text-3); }
    .my-rank-stat-val { font-weight: 600; color: var(--text); }
    .my-rank-stat-val.green { color: var(--accent); }

    /* Regole punteggio */
    .rules-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-sm);
        padding: 1.25rem 1.5rem;
    }

    .rules-title {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 1rem;
    }

    .rule-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 7px 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
    }

    .rule-row:last-child { border-bottom: none; }

    .rule-label { color: var(--text-2); }

    .rule-xp {
        font-weight: 700;
        color: var(--accent);
        font-size: 0.78rem;
    }

    /* ── STATI LOADING / ERROR / EMPTY ── */
    .state-box {
        padding: 4rem 2rem;
        text-align: center;
    }

    .state-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }

    .state-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.2rem;
        color: var(--text);
        margin-bottom: 6px;
        letter-spacing: -0.02em;
    }

    .state-sub {
        font-size: 0.8rem;
        color: var(--text-3);
        line-height: 1.55;
    }

    /* Skeleton loader */
    .skeleton-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.9rem 1.75rem;
        border-bottom: 1px solid var(--border);
    }

    .sk {
        background: linear-gradient(90deg, #F2F1ED 25%, #E8E6E0 50%, #F2F1ED 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
        border-radius: 6px;
    }

    @keyframes shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* ── MY RANK PLACEHOLDER (backend non pronto) ── */
    .pending-notice {
        background: #FFF8E7;
        border: 1px solid var(--gold-border);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 0.76rem;
        color: #8B6914;
        line-height: 1.5;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
</style>

<div class="page-header">
    <div>
        <p class="page-eyebrow">Gamification</p>
        <h1 class="page-title">Classifica</h1>
    </div>
    <button class="refresh-btn" id="refresh-btn" onclick="loadLeaderboard()">
        <svg id="refresh-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"></polyline>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
        </svg>
        Aggiorna
    </button>
</div>

<div class="leaderboard-layout">

    {{-- ── COLONNA PRINCIPALE ── --}}
    <div>
        <div class="gs-card" id="leaderboard-card">

            {{-- Skeleton visibile durante il caricamento --}}
            <div id="state-loading">
                <div style="background: linear-gradient(160deg,#1A3D2A,#2A6B4A); padding:2rem; height:180px; display:flex; align-items:center; justify-content:center;">
                    <div style="display:flex;gap:2rem;align-items:flex-end;">
                        <div class="sk" style="width:80px;height:100px;border-radius:14px;opacity:0.3;"></div>
                        <div class="sk" style="width:80px;height:130px;border-radius:14px;opacity:0.3;"></div>
                        <div class="sk" style="width:80px;height:80px;border-radius:14px;opacity:0.3;"></div>
                    </div>
                </div>
                @for($i = 0; $i < 6; $i++)
                <div class="skeleton-row">
                    <div class="sk" style="width:28px;height:20px;"></div>
                    <div class="sk" style="width:38px;height:38px;border-radius:10px;flex-shrink:0;"></div>
                    <div style="flex:1;">
                        <div class="sk" style="width:55%;height:14px;margin-bottom:6px;"></div>
                        <div class="sk" style="width:35%;height:11px;"></div>
                    </div>
                    <div class="sk" style="width:50px;height:20px;"></div>
                </div>
                @endfor
            </div>

            {{-- Stato errore --}}
            <div id="state-error" style="display:none;">
                <div class="state-box">
                    <div class="state-icon">⚠️</div>
                    <p class="state-title">Classifica non disponibile</p>
                    <p class="state-sub">L'endpoint <code>/api/gamification/leaderboard</code> non è ancora raggiungibile.<br>Sarà disponibile al completamento del task 3.6.</p>
                </div>
            </div>

            {{-- Stato vuoto --}}
            <div id="state-empty" style="display:none;">
                <div class="state-box">
                    <div class="state-icon">🏆</div>
                    <p class="state-title">Nessun dato ancora</p>
                    <p class="state-sub">Completa la tua prima sessione di ricarica per entrare in classifica!</p>
                </div>
            </div>

            {{-- Contenuto reale --}}
            <div id="state-content" style="display:none;">

                {{-- Podio (top 3) --}}
                <div class="podio-section">
                    <p class="podio-label">Top 3 questa settimana</p>
                    <div class="podio-row" id="podio-row">
                        {{-- Popolato via JS --}}
                    </div>
                </div>

                {{-- Lista dal 4° in poi --}}
                <div class="rank-list" id="rank-list">
                    {{-- Popolato via JS --}}
                </div>

            </div>

        </div>
    </div>

    {{-- ── SIDEBAR ── --}}
    <div class="sidebar-col">

        {{-- La tua posizione --}}
        <div class="my-rank-card" id="my-rank-card">
            <div class="my-rank-top">
                <div class="my-rank-circle">
                    <span class="my-rank-num" id="my-pos">—</span>
                    <span class="my-rank-of">posto</span>
                </div>
                <div class="my-rank-info">
                    <p class="my-rank-label">La tua posizione</p>
                    <p class="my-rank-name">{{ Auth::user()->nome }} {{ Auth::user()->cognome }}</p>
                    <p class="my-rank-xp" id="my-xp-label">— XP totali</p>
                </div>
            </div>
            <div class="my-rank-bottom">
                <div class="my-rank-stat">
                    <span class="my-rank-stat-label">XP questa settimana</span>
                    <span class="my-rank-stat-val green" id="my-xp-week">—</span>
                </div>
                <div class="my-rank-stat">
                    <span class="my-rank-stat-label">Sessioni totali</span>
                    <span class="my-rank-stat-val" id="my-sessions">—</span>
                </div>
                <div class="my-rank-stat">
                    <span class="my-rank-stat-label">CO₂ risparmiata</span>
                    <span class="my-rank-stat-val green" id="my-co2">—</span>
                </div>
            </div>
        </div>

        {{-- Regole punteggio --}}
        <div class="rules-card">
            <p class="rules-title">Come si guadagnano XP</p>
            <div class="rule-row">
                <span class="rule-label">Sessione completata</span>
                <span class="rule-xp">+50 XP</span>
            </div>
            <div class="rule-row">
                <span class="rule-label">Prima ricarica</span>
                <span class="rule-xp">+100 XP</span>
            </div>
            <div class="rule-row">
                <span class="rule-label">Streak 7 giorni</span>
                <span class="rule-xp">+200 XP</span>
            </div>
            <div class="rule-row">
                <span class="rule-label">Badge sbloccato</span>
                <span class="rule-xp">+50 XP</span>
            </div>
            <div class="rule-row">
                <span class="rule-label">kWh erogati (×1)</span>
                <span class="rule-xp">+5 XP/kWh</span>
            </div>
        </div>

    </div>
</div>

<script>
    const API_TOKEN = "{{ session('api_token') }}";
    const MY_ID     = "{{ Auth::user()->id_utente }}";
    const MY_NAME   = "{{ Auth::user()->nome }} {{ Auth::user()->cognome }}";

    function initials(nome, cognome) {
        return ((nome?.[0] ?? '') + (cognome?.[0] ?? '')).toUpperCase();
    }

    function setState(name) {
        ['loading', 'error', 'empty', 'content'].forEach(s => {
            document.getElementById('state-' + s).style.display = (s === name) ? '' : 'none';
        });
    }

    function buildPodio(top3) {
        // ordine visivo: 2° - 1° - 3°
        const slots = [top3[1], top3[0], top3[2]];
        const posClass = ['pos-2', 'pos-1', 'pos-3'];
        const ranks    = ['2°', '1°', '3°'];
        const rankPos  = [1, 0, 2]; // indice originale per pos-N

        const container = document.getElementById('podio-row');
        container.innerHTML = '';

        slots.forEach((user, vi) => {
            const realPos = rankPos[vi] + 1;
            const div = document.createElement('div');
            div.className = 'podio-slot' + (user ? '' : ' empty');

            if (user) {
                const isMe = user.id_utente === MY_ID;
                div.innerHTML = `
                    <div class="podio-avatar ${posClass[vi]}">
                        ${realPos === 1 ? '<span class="podio-crown">👑</span>' : ''}
                        ${initials(user.nome, user.cognome)}
                    </div>
                    <span class="podio-name">${user.nome} ${user.cognome}${isMe ? ' (Tu)' : ''}</span>
                    <span class="podio-xp ${posClass[vi]}">${user.xp_totali} <small style="font-size:0.65rem;opacity:0.7;">XP</small></span>
                    <span class="podio-rank ${posClass[vi]}">${ranks[vi]}</span>
                `;
            } else {
                div.innerHTML = `
                    <div class="podio-avatar ${posClass[vi]}" style="font-size:1.2rem;color:rgba(255,255,255,0.2);">—</div>
                    <span class="podio-name">—</span>
                    <span class="podio-xp ${posClass[vi]}" style="opacity:0.2;">${ranks[vi]}</span>
                `;
            }

            // staggered animation delay
            div.style.animationDelay = (vi * 0.08) + 's';
            container.appendChild(div);
        });
    }

    function buildList(users) {
        // users già ordinati per rank, mostra dal 4° in poi
        const rest = users.slice(3);
        const container = document.getElementById('rank-list');
        container.innerHTML = '';

        if (rest.length === 0) {
            container.innerHTML = `<div style="padding:1.5rem 1.75rem; font-size:0.8rem; color:var(--text-3); text-align:center;">Solo i primi 3 — aggiungi altri utenti!</div>`;
            return;
        }

        rest.forEach((user, i) => {
            const pos = i + 4;
            const isMe = user.id_utente === MY_ID;
            const div = document.createElement('div');
            div.className = 'rank-item' + (isMe ? ' is-me' : '');
            div.style.animationDelay = (i * 0.04 + 0.1) + 's';
            div.innerHTML = `
                <span class="rank-pos">${pos}</span>
                <div class="rank-avatar">${initials(user.nome, user.cognome)}</div>
                <div class="rank-info">
                    <span class="rank-name">
                        ${user.nome} ${user.cognome}
                        ${isMe ? '<span class="rank-you-badge">Tu</span>' : ''}
                    </span>
                    <span class="rank-meta">${user.sessioni_totali ?? '—'} sessioni · ${user.co2_risparmiata_kg ?? '—'} kg CO₂</span>
                </div>
                <span class="rank-xp">${user.xp_totali} <span class="rank-xp-unit">XP</span></span>
            `;
            container.appendChild(div);
        });
    }

    function updateMyRank(users) {
        const myEntry = users.find(u => u.id_utente === MY_ID);
        if (!myEntry) return;

        const pos = users.indexOf(myEntry) + 1;
        document.getElementById('my-pos').textContent        = pos + (pos > 3 ? '°' : pos === 1 ? '°' : '°');
        document.getElementById('my-xp-label').textContent   = myEntry.xp_totali + ' XP totali';
        document.getElementById('my-xp-week').textContent    = (myEntry.xp_settimana ?? '—') + ' XP';
        document.getElementById('my-sessions').textContent   = (myEntry.sessioni_totali ?? '—');
        document.getElementById('my-co2').textContent        = (myEntry.co2_risparmiata_kg ?? '—') + ' kg';
    }

    async function loadLeaderboard() {
        setState('loading');

        const btn  = document.getElementById('refresh-btn');
        const icon = document.getElementById('refresh-icon');
        btn.disabled = true;
        btn.classList.add('spinning');

        try {
            const res = await fetch('/api/gamification/leaderboard', {
                headers: {
                    'Authorization': 'Bearer ' + API_TOKEN,
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const json = await res.json();

            // Supporta sia { data: [...] } sia [...] direttamente
            const users = Array.isArray(json) ? json : (json.data ?? []);

            if (users.length === 0) {
                setState('empty');
                return;
            }

            buildPodio(users.slice(0, 3));
            buildList(users);
            updateMyRank(users);
            setState('content');

        } catch (err) {
            console.warn('Leaderboard API non raggiungibile:', err.message);
            setState('error');
        } finally {
            btn.disabled = false;
            btn.classList.remove('spinning');
        }
    }

    loadLeaderboard();
</script>

@endsection