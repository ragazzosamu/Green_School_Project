@extends('layouts.app')

@section('content')

<style>
    /* Font caricati in layouts/app.blade.php — @import rimosso per non rompere la navbar sticky */

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
    .page-header { margin-bottom: 2rem; }
    .page-eyebrow {
        font-size: 0.7rem; font-weight: 600; letter-spacing: 0.12em;
        text-transform: uppercase; color: var(--text-3); margin-bottom: 5px;
    }
    .page-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.75rem; color: var(--text);
        letter-spacing: -0.03em; line-height: 1;
    }

    /* ── LAYOUT ── */
    .profile-grid {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 1.25rem;
        align-items: start;
    }
    @media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } }

    /* ── CARD ── */
    .gs-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    /* ── HERO ── */
    .hero-card-top {
        background: linear-gradient(160deg, #1A3D2A 0%, #2A6B4A 100%);
        padding: 2rem 1.75rem 1.5rem;
        position: relative; overflow: hidden;
    }
    .hero-card-top::before {
        content:''; position:absolute; top:-40px; right:-40px;
        width:180px; height:180px; border-radius:50%;
        background:rgba(255,255,255,0.04);
    }
    .hero-card-top::after {
        content:''; position:absolute; bottom:-20px; left:-20px;
        width:120px; height:120px; border-radius:50%;
        background:rgba(255,255,255,0.03);
    }
    .hero-avatar {
        width:56px; height:56px; border-radius:14px;
        background:rgba(255,255,255,0.15);
        display:flex; align-items:center; justify-content:center;
        font-size:24px; margin-bottom:1rem;
        border:1px solid rgba(255,255,255,0.2);
    }
    .hero-name {
        font-family:'DM Serif Display',Georgia,serif;
        font-size:1.4rem; color:#fff;
        letter-spacing:-0.02em; line-height:1.1; margin-bottom:4px;
    }
    .hero-role {
        font-size:0.75rem; color:rgba(255,255,255,0.5);
        font-weight:400; text-transform:uppercase; letter-spacing:0.08em;
    }
    .hero-level-row {
        display:flex; align-items:center; justify-content:space-between;
        margin-top:1.25rem; margin-bottom:0.5rem;
    }
    .hero-level-label { font-size:0.7rem; color:rgba(255,255,255,0.5); font-weight:500; letter-spacing:0.08em; text-transform:uppercase; }
    .hero-level-val   { font-family:'DM Serif Display',Georgia,serif; font-size:1rem; color:#7EEAAA; }
    .xp-track { height:6px; background:rgba(255,255,255,0.12); border-radius:100px; overflow:hidden; }
    .xp-bar   { height:100%; border-radius:100px; background:linear-gradient(90deg,#7EEAAA,#4ADE80); transition:width 1s cubic-bezier(0.25,1,0.5,1); }
    .xp-sub   { font-size:0.68rem; color:rgba(255,255,255,0.35); margin-top:5px; text-align:right; }

    /* Stats */
    .hero-card-bottom { padding:1.25rem 1.75rem; }
    .stats-row { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    .stat-item { background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:0.9rem 1rem; }
    .stat-item-label { font-size:0.62rem; font-weight:600; letter-spacing:0.09em; text-transform:uppercase; color:var(--text-3); margin-bottom:4px; }
    .stat-item-val   { font-family:'DM Serif Display',Georgia,serif; font-size:1.6rem; color:var(--text); line-height:1; letter-spacing:-0.02em; }
    .stat-item-val.green { color:var(--accent); }
    .stat-item-val.gold  { color:var(--gold); }
    .stat-item-unit  { font-family:'DM Sans',sans-serif; font-size:0.75rem; color:var(--text-3); font-weight:400; margin-left:2px; }

    /* Badge */
    .section-label { font-size:0.68rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-3); padding:1.1rem 1.5rem 0.6rem; border-top:1px solid var(--border); }
    .section-label:first-child { border-top:none; }
    .badge-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; padding:0 1.5rem 1.5rem; }
    .badge-item { border-radius:12px; border:1px solid var(--border); padding:0.85rem 1rem; display:flex; align-items:center; gap:10px; transition:box-shadow 0.15s; }
    .badge-item:hover { box-shadow:var(--shadow-sm); }
    .badge-item.unlocked { background:var(--gold-bg); border-color:#E8D5A0; }
    .badge-item.locked   { background:var(--surface2); opacity:0.55; filter:grayscale(0.4); }
    .badge-emoji      { font-size:1.5rem; flex-shrink:0; line-height:1; }
    .badge-emoji.locked-icon { font-size:1.1rem; opacity:0.4; }
    .badge-name  { font-size:0.75rem; font-weight:600; color:var(--text); line-height:1.3; }
    .badge-desc  { font-size:0.65rem; color:var(--text-3); margin-top:1px; }
    .badge-date  { font-size:0.6rem; color:var(--gold); font-weight:500; margin-top:2px; }

    /* Colonna destra */
    .right-col { display:flex; flex-direction:column; gap:1.25rem; }

    /* Streak */
    .streak-card-inner { padding:1.5rem; display:flex; align-items:center; gap:1.25rem; }
    .streak-circle {
        width:72px; height:72px; border-radius:50%;
        background:linear-gradient(135deg,#FFF3CC,#FFE082);
        border:2px solid #E8D5A0;
        display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0;
    }
    .streak-num   { font-family:'DM Serif Display',Georgia,serif; font-size:1.6rem; color:#8B6914; line-height:1; }
    .streak-label { font-size:0.55rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; color:#A07820; margin-top:1px; }
    .streak-info-title { font-family:'DM Serif Display',Georgia,serif; font-size:1.1rem; color:var(--text); margin-bottom:3px; }
    .streak-info-sub   { font-size:0.78rem; color:var(--text-3); line-height:1.5; }

    /* XP hero */
    .xp-hero-inner { padding:1.75rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .xp-hero-label  { font-size:0.68rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--text-3); margin-bottom:6px; }
    .xp-hero-number { font-family:'DM Serif Display',Georgia,serif; font-size:3.5rem; color:var(--accent); letter-spacing:-0.04em; line-height:1; }
    .xp-hero-unit   { font-size:1rem; color:var(--text-3); font-weight:300; margin-left:4px; }
    .xp-hero-sub    { font-size:0.78rem; color:var(--text-3); margin-top:6px; }
    .level-badge { display:inline-flex; flex-direction:column; align-items:center; background:var(--accent-bg); border:1px solid #C5E0D0; border-radius:14px; padding:12px 18px; min-width:80px; }
    .level-badge-num   { font-family:'DM Serif Display',Georgia,serif; font-size:2rem; color:var(--accent); line-height:1; }
    .level-badge-label { font-size:0.6rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--accent-mid); margin-top:3px; }

    /* Skeleton loader */
    .skel { background:linear-gradient(90deg,#E4E2DA 25%,#EDEBE5 50%,#E4E2DA 75%); background-size:200% 100%; animation:skel-anim 1.4s infinite; border-radius:6px; display:inline-block; }
    @keyframes skel-anim { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    /* Banner sessione (invariato) */
    .session-banner { display:none; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:1.25rem 1.5rem; margin-bottom:1.5rem; box-shadow:var(--shadow-sm); align-items:center; gap:1.25rem; }
    .session-banner.show { display:flex; }
    .session-banner.attesa { border-color:#F4D58D; background:#FEF8E6; }
    .session-banner.attiva { border-color:#BBF7D0; background:#F0FDF4; }
    .session-banner-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--surface2); font-size:22px; flex-shrink:0; }
    .session-banner.attesa .session-banner-icon { background:#FCEFCB; }
    .session-banner.attiva .session-banner-icon { background:#DCFCE7; }
    .session-banner-body { flex:1; min-width:0; }
    .session-banner-title { font-family:'DM Sans',sans-serif; font-weight:600; font-size:0.95rem; color:var(--text); margin-bottom:4px; }
    .session-banner-sub   { font-size:0.8rem; color:var(--text-2); }
    .session-banner-sub strong { color:var(--text); font-weight:600; }
    .session-banner-cta { background:var(--accent); color:#fff; border:none; border-radius:10px; padding:9px 16px; font-size:0.82rem; font-weight:600; cursor:pointer; text-decoration:none; white-space:nowrap; }
    .session-banner-cta:hover { background:#1f5238; }
    .attesa-timer { display:inline-flex; align-items:baseline; gap:4px; margin-top:8px; padding:4px 12px; background:rgba(212,160,23,0.15); border:1px solid rgba(212,160,23,0.35); border-radius:100px; }
    .attesa-timer-value { font-family:'DM Serif Display',Georgia,serif; font-size:1.1rem; color:#8B6914; line-height:1; }
    .attesa-timer-label { font-size:0.7rem; color:#8B6914; font-weight:500; }
    .attesa-progress { height:3px; background:rgba(212,160,23,0.2); border-radius:100px; overflow:hidden; margin-top:8px; }
    .attesa-progress-bar { height:100%; background:linear-gradient(90deg,#D4A017,#F4D58D); width:100%; transition:width 0.9s linear; }
</style>

<div class="page-header">
    <p class="page-eyebrow">Il tuo profilo</p>
    <h1 class="page-title">Gamification</h1>
</div>

{{-- ── Banner sessione: INVARIATO — non toccare ─────────────────────────── --}}
@php
    if ($sessione_attiva) {
        $bannerStato  = 'attiva';
        $bannerIcona  = '⚡';
        $bannerTitolo = 'Sessione di ricarica in corso';
        $bannerSub    = 'Energia erogata: <strong id="banner-kwh">' . number_format((float)($kwh_attuali ?? 0), 2) . ' kWh</strong>';
    } elseif (!empty($attesa_punto)) {
        $bannerStato  = 'attesa';
        $bannerIcona  = '🔌';
        $bannerTitolo = 'In attesa del cavo';
        $bannerSub    = 'Collega il cavo alla presa per avviare la ricarica.'
            . '<div class="attesa-timer">'
            . '  <span class="attesa-timer-value" id="attesa-timer-value">60</span>'
            . '  <span class="attesa-timer-label">secondi rimanenti</span>'
            . '</div>'
            . '<div class="attesa-progress"><div class="attesa-progress-bar" id="attesa-progress-bar" style="width:100%"></div></div>';
    } else {
        $bannerStato  = '';
        $bannerIcona  = '';
        $bannerTitolo = '';
        $bannerSub    = '';
    }
@endphp

<div id="session-banner"
     class="session-banner {{ $bannerStato ? 'show ' . $bannerStato : '' }}"
     data-id-stazione="{{ $sessione_attiva->id_stazione ?? $attesa_stazione ?? '' }}"
     data-id-punto="{{ $sessione_attiva->id_punto ?? $attesa_punto ?? '' }}"
     data-id-sessione="{{ $sessione_attiva->id_sessione ?? '' }}">
    <div id="session-banner-icon" class="session-banner-icon">{{ $bannerIcona }}</div>
    <div class="session-banner-body">
        <p id="session-banner-title" class="session-banner-title">{{ $bannerTitolo }}</p>
        <p id="session-banner-sub"   class="session-banner-sub">{!! $bannerSub !!}</p>
    </div>
    <a id="session-banner-cta"
       class="session-banner-cta"
       href="{{ $sessione_attiva ? '/session/' . $sessione_attiva->id_sessione : '#' }}"
       style="{{ $sessione_attiva ? '' : 'display:none;' }}">
        Vai alla sessione →
    </a>
</div>
{{-- ── Fine banner sessione ─────────────────────────────────────────────── --}}

<div class="profile-grid">

    {{-- ── COLONNA SINISTRA ── --}}
    <div class="gs-card">

        {{-- Hero --}}
        <div class="hero-card-top">
            <div class="hero-avatar">🌿</div>
            <p class="hero-name">{{ Auth::user()->nome }} {{ Auth::user()->cognome }}</p>
            <p class="hero-role">{{ Auth::user()->tipo_account ?? 'Studente' }}</p>

            <div class="hero-level-row">
                <span class="hero-level-label" id="hero-level-label">
                    <span class="skel" style="width:80px;height:10px;"></span>
                </span>
                <span class="hero-level-val" id="hero-xp-fraction">
                    <span class="skel" style="width:90px;height:10px;"></span>
                </span>
            </div>
            <div class="xp-track">
                <div class="xp-bar" id="xp-bar" style="width:0%"></div>
            </div>
            <p class="xp-sub" id="xp-sub">
                <span class="skel" style="width:120px;height:8px;"></span>
            </p>
        </div>

        {{-- Stats --}}
        <div class="hero-card-bottom">
            <div class="stats-row">
                <div class="stat-item">
                    <p class="stat-item-label">CO₂ risparmiata</p>
                    <p class="stat-item-val green" id="stat-co2">
                        <span class="skel" style="width:40px;height:26px;"></span>
                    </p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">Sessioni totali</p>
                    <p class="stat-item-val" id="stat-sessioni">
                        <span class="skel" style="width:30px;height:26px;"></span>
                    </p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">kWh erogati</p>
                    <p class="stat-item-val green" id="stat-kwh">
                        <span class="skel" style="width:40px;height:26px;"></span>
                    </p>
                </div>
                <div class="stat-item">
                    <p class="stat-item-label">Streak</p>
                    <p class="stat-item-val gold" id="stat-streak">
                        <span class="skel" style="width:30px;height:26px;"></span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Badge sbloccati --}}
        <p class="section-label">Badge sbloccati</p>
        <div class="badge-grid" id="badge-sbloccati-grid">
            <div class="badge-item" style="opacity:.4;">
                <span class="skel" style="width:32px;height:32px;border-radius:8px;"></span>
                <div><span class="skel" style="width:70px;height:10px;display:block;margin-bottom:4px;"></span><span class="skel" style="width:90px;height:8px;display:block;"></span></div>
            </div>
        </div>

        {{-- Badge da sbloccare --}}
        <p class="section-label">Da sbloccare</p>
        <div class="badge-grid" id="badge-locked-grid" style="padding-bottom:1.5rem;">
            <div class="badge-item locked">
                <span class="skel" style="width:28px;height:28px;border-radius:6px;opacity:.5;"></span>
                <div><span class="skel" style="width:70px;height:10px;display:block;margin-bottom:4px;"></span><span class="skel" style="width:90px;height:8px;display:block;"></span></div>
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
                    <p class="xp-hero-number" id="xp-totali-big">
                        <span class="skel" style="width:120px;height:56px;"></span>
                    </p>
                    <p class="xp-hero-sub">Guadagni XP completando sessioni di ricarica</p>
                </div>
                <div class="xp-hero-right">
                    <div class="level-badge">
                        <span class="level-badge-num" id="livello-badge">–</span>
                        <span class="level-badge-label">Livello</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Streak --}}
        <div class="gs-card">
            <div class="streak-card-inner">
                <div class="streak-circle">
                    <span class="streak-num" id="streak-num">–</span>
                    <span class="streak-label">giorni</span>
                </div>
                <div>
                    <p class="streak-info-title" id="streak-title">Streak 🔥</p>
                    <p class="streak-info-sub" id="streak-sub">
                        <span class="skel" style="width:200px;height:10px;display:block;margin-bottom:4px;"></span>
                        <span class="skel" style="width:160px;height:10px;display:block;"></span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Sfide settimanali --}}
        <div class="gs-card">
            <p class="section-label" style="border-top:none; padding-top:1.1rem;">Sfide della settimana</p>
            <div id="sfide-list" style="padding:0 1.5rem 1.5rem; display:flex; flex-direction:column; gap:0.7rem;">
                <span class="skel" style="width:100%;height:72px;border-radius:12px;display:block;"></span>
                <span class="skel" style="width:100%;height:72px;border-radius:12px;display:block;"></span>
                <span class="skel" style="width:100%;height:72px;border-radius:12px;display:block;"></span>
            </div>
        </div>

        {{-- Ultime sessioni --}}
        <div class="gs-card">
            <p class="section-label" style="border-top:none; padding-top:1.1rem;">Ultime sessioni</p>
            <div id="sessioni-list" style="padding:0 1.5rem 1.5rem; display:flex; flex-direction:column; gap:0.6rem;">
                <span class="skel" style="width:100%;height:52px;border-radius:12px;display:block;"></span>
                <span class="skel" style="width:100%;height:52px;border-radius:12px;display:block;"></span>
                <span class="skel" style="width:100%;height:52px;border-radius:12px;display:block;"></span>
            </div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// BANNER SESSIONE — INVARIATO (non modificato)
// ─────────────────────────────────────────────────────────────────────────────
const ID_UTENTE       = @json(Auth::user()?->id_utente);
const ATTESA_PUNTO    = @json($attesa_punto ?? null);
const ATTESA_STAZIONE = @json($attesa_stazione ?? null);
const API_TOKEN       = @json($api_token ?? '');

// id_punto e' locale alla stazione ("1", "2", ...), quindi i canali per-punto
// devono includere il MAC normalizzato (senza ":") per non collidere fra
// stazioni diverse. Stesso schema usato lato backend (PuntoStatusChanged).
function nomeCanalePunto(idStazione, idPunto) {
    if (!idStazione || !idPunto) return null;
    return 'punto.' + idStazione.replace(/:/g, '') + '.' + idPunto;
}

const banner      = document.getElementById('session-banner');
const bannerIcon  = document.getElementById('session-banner-icon');
const bannerTitle = document.getElementById('session-banner-title');
const bannerSub   = document.getElementById('session-banner-sub');
const bannerCta   = document.getElementById('session-banner-cta');

let idStazioneCorrente = banner.dataset.idStazione || ATTESA_STAZIONE || null;
let idPuntoCorrente    = banner.dataset.idPunto    || ATTESA_PUNTO    || null;
let idSessioneCorrente = banner.dataset.idSessione || null;
let kwhCorrenti        = parseFloat(@json((float) ($kwh_attuali ?? 0))) || 0;

let echo = null;
try {
    window.Pusher = Pusher;
    const _isHttps = window.location.protocol === 'https:';
    echo = new Echo({
        broadcaster: 'reverb',
        key:    '{{ env("VITE_REVERB_APP_KEY") }}',
        wsHost:  window.location.hostname,
        wsPort:  _isHttps ? 443 : 80,
        wssPort: 443,
        forceTLS: _isHttps,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        },
    });
    window.Echo = echo;
} catch (err) {
    console.error('[profilo] Echo init fallito:', err);
}

if (echo && ID_UTENTE) {
    ascoltaUtente(ID_UTENTE);
}

// ── Countdown: usa sessionStorage per sopravvivere alla navigazione ──────────
// Il problema: $attesa_punto viene passato dal PHP solo se l'URL ha ?attesa=xxx
// (es. /profilo?attesa=PUNTO_01). Se l'utente va su /classifica e torna su
// /profilo (senza query param), il PHP non sa più dell'attesa e il banner
// non viene renderizzato. Soluzione: salviamo id_punto e timestamp in
// sessionStorage e ricostruiamo il banner lato JS se necessario.

const SK_ATTESA_START    = 'gs_attesa_start';
const SK_ATTESA_PUNTO    = 'gs_attesa_punto';
const SK_ATTESA_STAZIONE = 'gs_attesa_stazione';
const TTL_QR = 60; // deve coincidere con SessioneService::TTL_CODICE_PENDING

let countdownInterval = null;

if (banner.classList.contains('attesa')) {
    // Pagina caricata con attesa attiva dall'URL: salvo tutto in sessionStorage
    if (!sessionStorage.getItem(SK_ATTESA_START)) {
        sessionStorage.setItem(SK_ATTESA_START, Date.now().toString());
    }
    if (ATTESA_PUNTO) {
        sessionStorage.setItem(SK_ATTESA_PUNTO, ATTESA_PUNTO);
    }
    if (ATTESA_STAZIONE) {
        sessionStorage.setItem(SK_ATTESA_STAZIONE, ATTESA_STAZIONE);
    }
    avviaORestituisciCountdown();

} else if (!banner.classList.contains('attiva') && !banner.classList.contains('show')) {
    // Banner non mostrato dal PHP: controlla se c'è un'attesa salvata in sessionStorage
    const puntoInAttesa    = sessionStorage.getItem(SK_ATTESA_PUNTO);
    const stazioneInAttesa = sessionStorage.getItem(SK_ATTESA_STAZIONE);
    const secondiRimanenti = calcolaSecondiRimanenti();

    if (puntoInAttesa && secondiRimanenti > 0) {
        // L'utente era tornato sul profilo senza query param ma l'attesa è ancora valida
        idPuntoCorrente    = puntoInAttesa;
        idStazioneCorrente = stazioneInAttesa;
        ricostruisciBannerAttesa(secondiRimanenti);
        avviaORestituisciCountdown();
        // Ricollegati al canale WS del punto (era perso al cambio pagina)
        const canale = nomeCanalePunto(stazioneInAttesa, puntoInAttesa);
        if (echo && canale) {
            echo.channel(canale).listen('.punto.status', (e) => {
                if (e.libera === true || e.libera === 1) { nascondi(); }
            });
        }
    } else if (puntoInAttesa && secondiRimanenti <= 0) {
        // Attesa scaduta mentre navigava
        sessionStorage.removeItem(SK_ATTESA_START);
        sessionStorage.removeItem(SK_ATTESA_PUNTO);
        sessionStorage.removeItem(SK_ATTESA_STAZIONE);
    }
}

function ricostruisciBannerAttesa(secondiRimanenti) {
    banner.classList.add('show', 'attesa');
    bannerIcon.textContent  = '🔌';
    bannerTitle.textContent = 'In attesa del cavo';
    bannerSub.innerHTML =
        'Collega il cavo alla presa per avviare la ricarica.' +
        '<div class="attesa-timer">' +
        '  <span class="attesa-timer-value" id="attesa-timer-value">' + secondiRimanenti + '</span>' +
        '  <span class="attesa-timer-label">secondi rimanenti</span>' +
        '</div>' +
        '<div class="attesa-progress"><div class="attesa-progress-bar" id="attesa-progress-bar" style="width:' +
        Math.round((secondiRimanenti / TTL_QR) * 100) + '%"></div></div>';
    bannerCta.style.display = 'none';
}

function avviaORestituisciCountdown() {
    const secondiRimanenti = calcolaSecondiRimanenti();
    if (secondiRimanenti > 0) {
        avviaCountdown(secondiRimanenti);
    } else {
        sessionStorage.removeItem(SK_ATTESA_START);
        sessionStorage.removeItem(SK_ATTESA_PUNTO);
        if (!idSessioneCorrente) {
            bannerTitle.textContent = 'Tempo scaduto';
            bannerSub.textContent   = 'Il cavo non è stato collegato in tempo. Riprova dalla mappa.';
        }
    }
}

function calcolaSecondiRimanenti() {
    const start = parseInt(sessionStorage.getItem(SK_ATTESA_START) || '0', 10);
    if (!start) return TTL_QR;
    const trascorsi = Math.floor((Date.now() - start) / 1000);
    return Math.max(0, TTL_QR - trascorsi);
}

function avviaCountdown(secondiIniziali) {
    const valEl = document.getElementById('attesa-timer-value');
    const barEl = document.getElementById('attesa-progress-bar');
    if (!valEl) return;
    let rimanenti = secondiIniziali;
    valEl.textContent = rimanenti;
    if (barEl) barEl.style.width = Math.round((rimanenti / TTL_QR) * 100) + '%';

    countdownInterval = setInterval(() => {
        rimanenti -= 1;
        valEl.textContent = Math.max(0, rimanenti);
        if (barEl) barEl.style.width = Math.round(Math.max(0, rimanenti / TTL_QR) * 100) + '%';
        if (rimanenti <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            sessionStorage.removeItem(SK_ATTESA_START);
            if (!idSessioneCorrente) {
                bannerTitle.textContent = 'Tempo scaduto';
                bannerSub.textContent   = 'Il cavo non è stato collegato in tempo. Riprova dalla mappa.';
            }
        }
    }, 1000);
}

function fermaCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    // Nasconde fisicamente il timer dal DOM (bug fix: non rimane "53s bloccato")
    const timerEl = document.getElementById('attesa-timer-value');
    if (timerEl) {
        const wrap = timerEl.closest('.attesa-timer');
        if (wrap) wrap.style.display = 'none';
    }
    const barWrap = document.getElementById('attesa-progress-bar');
    if (barWrap) {
        const progressEl = barWrap.closest('.attesa-progress');
        if (progressEl) progressEl.style.display = 'none';
    }
    // Pulisco sessionStorage: prenotazione conclusa (con successo o scaduta)
    sessionStorage.removeItem(SK_ATTESA_START);
    sessionStorage.removeItem(SK_ATTESA_PUNTO);
    sessionStorage.removeItem(SK_ATTESA_STAZIONE);
}

function ascoltaUtente(idUtente) {
    const ch = echo.private('user.' + idUtente);
    ch.listen('.sessione.avviata', (e) => {
        idSessioneCorrente = e.id_sessione;
        idPuntoCorrente    = e.id_punto;
        idStazioneCorrente = e.id_stazione || idStazioneCorrente;
        kwhCorrenti        = 0;
        mostraAttiva();
    });
    ch.listen('.ricarica.heartbeat', (e) => {
        if (idSessioneCorrente && e.id_sessione && e.id_sessione !== idSessioneCorrente) return;
        kwhCorrenti += Number(e.cambiamento_kwh) || 0;
        aggiornaKwhUI();
    });
    const canale = nomeCanalePunto(idStazioneCorrente, idPuntoCorrente);
    if (canale) {
        echo.channel(canale).listen('.punto.status', (e) => {
            if (e.libera === true || e.libera === 1) { nascondi(); }
        });
    }
}
function mostraAttiva() {
    fermaCountdown();
    banner.classList.remove('attesa');
    banner.classList.add('show', 'attiva');
    bannerIcon.textContent  = '⚡';
    bannerTitle.textContent = 'Sessione di ricarica in corso';
    aggiornaKwhUI();
    bannerCta.href  = '/session/' + idSessioneCorrente;
    bannerCta.style.display = 'inline-block';
}
function aggiornaKwhUI() {
    bannerSub.innerHTML = 'Energia erogata: <strong>' + kwhCorrenti.toFixed(2) + ' kWh</strong>';
}
function nascondi() {
    banner.classList.remove('show', 'attesa', 'attiva');
    idSessioneCorrente = null;
    idPuntoCorrente    = null;
    idStazioneCorrente = null;
    sessionStorage.removeItem(SK_ATTESA_START);
    sessionStorage.removeItem(SK_ATTESA_PUNTO);
    sessionStorage.removeItem(SK_ATTESA_STAZIONE);
}
// ─────────────────────────────────────────────────────────────────────────────
// FINE BANNER SESSIONE
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// GAMIFICATION — fetch dati reali dal backend
// ─────────────────────────────────────────────────────────────────────────────

const HEADERS = {
    'Authorization': 'Bearer ' + API_TOKEN,
    'Accept': 'application/json',
};

async function caricaTutto() {
    try {
        const [resProfile, resBadges, resSessioni, resSfide] = await Promise.all([
            fetch('/api/gamification/profile',  { headers: HEADERS }),
            fetch('/api/gamification/badges',   { headers: HEADERS }),
            fetch('/api/gamification/sessioni', { headers: HEADERS }),
            fetch('/api/gamification/sfide',    { headers: HEADERS }),
        ]);

        if (resProfile.ok)  renderProfile(await resProfile.json());
        if (resBadges.ok)   renderBadges(await resBadges.json());
        if (resSessioni.ok) renderSessioni(await resSessioni.json());
        if (resSfide.ok)    renderSfide(await resSfide.json());

    } catch (err) {
        console.error('[gamification] caricaTutto fallito:', err);
    }
}

// ── Profile ──────────────────────────────────────────────────────────────────
function renderProfile(d) {
    // XP hero
    document.getElementById('xp-totali-big').innerHTML =
        d.xp_totali + '<span class="xp-hero-unit">XP</span>';
    document.getElementById('livello-badge').textContent = d.livello;

    // Barra livello (hero card)
    const livelloOra = d.livello;
    const livelloPros = livelloOra + 1;
    document.getElementById('hero-level-label').textContent =
        'Livello ' + livelloOra + ' → ' + livelloPros;
    document.getElementById('hero-xp-fraction').textContent =
        d.xp_nel_livello + ' / ' + d.xp_necessari + ' XP';
    document.getElementById('xp-bar').style.width = d.percentuale_livello + '%';
    const mancano = d.xp_necessari - d.xp_nel_livello;
    document.getElementById('xp-sub').textContent = mancano + ' XP al prossimo livello';

    // Stat cards
    document.getElementById('stat-co2').innerHTML =
        Math.round(d.co2_risparmiata_kg) + '<span class="stat-item-unit">kg</span>';
    document.getElementById('stat-sessioni').textContent = d.sessioni_totali;
    document.getElementById('stat-kwh').innerHTML =
        Math.round(d.kwh_totali) + '<span class="stat-item-unit">kWh</span>';
    document.getElementById('stat-streak').innerHTML =
        d.streak_giorni + '<span class="stat-item-unit">gg</span>';

    // Streak card
    document.getElementById('streak-num').textContent = d.streak_giorni;
    if (d.streak_giorni > 0) {
        document.getElementById('streak-title').textContent = 'Streak attiva 🔥';
        document.getElementById('streak-sub').innerHTML =
            'Hai ricaricato per <strong>' + d.streak_giorni + ' giorni consecutivi</strong>.<br>' +
            'Continua così per sbloccare altri badge!';
    } else {
        document.getElementById('streak-title').textContent = 'Nessuna streak attiva';
        document.getElementById('streak-sub').textContent =
            'Ricarica oggi per iniziare la tua streak!';
    }
}

// ── Badges ───────────────────────────────────────────────────────────────────
function renderBadges(d) {
    const gridSbloccati = document.getElementById('badge-sbloccati-grid');
    const gridLocked    = document.getElementById('badge-locked-grid');

    if (d.sbloccati.length === 0) {
        gridSbloccati.innerHTML =
            '<p style="font-size:0.78rem;color:var(--text-3);padding:0 0 1rem 0;grid-column:span 2;">' +
            'Nessun badge ancora sbloccato. Completa la prima ricarica!</p>';
    } else {
        gridSbloccati.innerHTML = d.sbloccati.map(b => {
            const data = b.data_sblocco
                ? new Date(b.data_sblocco).toLocaleDateString('it-IT', { day:'numeric', month:'short', year:'numeric' })
                : '';
            return `<div class="badge-item unlocked">
                <span class="badge-emoji">${b.icona_emoji}</span>
                <div>
                    <p class="badge-name">${b.nome}</p>
                    <p class="badge-desc">${b.descrizione}</p>
                    ${data ? `<p class="badge-date">${data}</p>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    if (d.da_sbloccare.length === 0) {
        gridLocked.innerHTML =
            '<p style="font-size:0.78rem;color:var(--accent);padding:0 0 1rem 0;grid-column:span 2;">🎉 Hai sbloccato tutti i badge!</p>';
    } else {
        gridLocked.innerHTML = d.da_sbloccare.map(b => {
            const cond = b.condizione_json?.descrizione ?? b.descrizione;
            return `<div class="badge-item locked">
                <span class="badge-emoji locked-icon">${b.icona_emoji}</span>
                <div>
                    <p class="badge-name">${b.nome}</p>
                    <p class="badge-desc">${cond}</p>
                </div>
            </div>`;
        }).join('');
    }
}

// ── Sessioni ─────────────────────────────────────────────────────────────────
function renderSessioni(d) {
    const list = document.getElementById('sessioni-list');

    if (!d.sessioni || d.sessioni.length === 0) {
        list.innerHTML =
            '<p style="font-size:0.78rem;color:var(--text-3);text-align:center;padding:1rem 0;">Nessuna sessione completata ancora.</p>';
        return;
    }

    list.innerHTML = d.sessioni.map(s => `
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;
                    padding:0.9rem 1.1rem;display:flex;align-items:center;justify-content:space-between;gap:0.75rem;">
            <div>
                <p style="font-size:0.8rem;font-weight:600;color:var(--text);">${s.data}</p>
                <p style="font-size:0.72rem;color:var(--text-3);">${s.durata} · ${s.kwh} kWh</p>
            </div>
            <span style="font-size:0.7rem;font-weight:700;color:var(--accent);background:var(--accent-bg);
                         border:1px solid #C5E0D0;border-radius:100px;padding:3px 10px;white-space:nowrap;">
                ${s.xp}
            </span>
        </div>
    `).join('');

    // Bug fix 1: /storico non esiste — la pagina profilo è già lo storico, link rimosso
    // (se in futuro viene creata la rotta /storico basta rimettere href="/storico")
}

// ── Sfide settimanali ────────────────────────────────────────────────────────
function renderSfide(d) {
    const list = document.getElementById('sfide-list');

    if (!d.sfide || d.sfide.length === 0) {
        list.innerHTML =
            '<p style="font-size:0.78rem;color:var(--text-3);text-align:center;padding:1rem 0;">Nessuna sfida disponibile.</p>';
        return;
    }

    list.innerHTML = d.sfide.map(s => {
        const completata = s.stato === 'completata';
        const badgeXp = completata
            ? `✓ +${s.bonus_xp} XP`
            : `+${s.bonus_xp} XP`;
        return `
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:0.9rem 1.1rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.35rem;">
                <span style="font-size:0.8rem;font-weight:600;color:var(--text);">${s.icona} ${s.titolo}</span>
                <span style="font-size:0.68rem;font-weight:700;white-space:nowrap;color:${completata ? 'var(--accent)' : 'var(--text-3)'};">
                    ${badgeXp}
                </span>
            </div>
            <p style="font-size:0.72rem;color:var(--text-3);margin-bottom:0.5rem;">${s.descrizione}</p>
            <div style="background:var(--border);border-radius:100px;height:7px;overflow:hidden;">
                <div style="height:100%;width:${s.percentuale}%;background:var(--accent);border-radius:100px;"></div>
            </div>
            <p style="font-size:0.66rem;color:var(--text-3);margin-top:4px;">
                ${s.progresso} / ${s.target} · ${s.percentuale}%
            </p>
        </div>`;
    }).join('');
}

// Carica al DOM ready
document.addEventListener('DOMContentLoaded', caricaTutto);
</script>

@endsection