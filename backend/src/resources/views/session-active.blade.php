@extends('layouts.app')

@section('content')

<style>
    /* Font già caricati in layouts/app.blade.php — @import rimosso */

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
        --red:       #DC2626;
        --red-bg:    #FEF2F2;
        --red-border:#FECACA;
    }

    .session-outer {
        max-width: 480px;
        margin: 0 auto;
    }

    /* Live badge */
    .live-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        padding: 6px 14px;
        font-size: 0.73rem;
        font-weight: 500;
        color: var(--text-2);
        letter-spacing: 0.04em;
        margin-bottom: 1.25rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    }

    .live-pulse {
        width: 7px; height: 7px;
        background: #16A34A;
        border-radius: 50%;
        animation: lp 1.4s ease-in-out infinite;
    }

    @keyframes lp {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(22,163,74,0.4); }
        50% { opacity: 0.7; box-shadow: 0 0 0 5px rgba(22,163,74,0); }
    }

    /* Main card */
    .session-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
        animation: cardIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Top section — kWh */
    .kwh-section {
        padding: 2.5rem 2rem 2rem;
        text-align: center;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, #F0FDF4 0%, var(--surface) 100%);
    }

    .kwh-label {
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 12px;
    }

    .kwh-row {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 6px;
        margin-bottom: 1.5rem;
    }

    .kwh-number {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 5.5rem;
        color: var(--text);
        letter-spacing: -0.04em;
        line-height: 1;
        transition: color 0.35s ease;
    }

    .kwh-number.flash { color: #16A34A; }

    .kwh-unit {
        font-family: 'DM Sans', sans-serif;
        font-size: 1.2rem;
        font-weight: 300;
        color: var(--text-3);
        margin-bottom: 6px;
    }

    /* Stats */
    .stats-section {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--border);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .stat-box {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        transition: border-color 0.3s, background 0.3s;
    }

    .stat-box.flash-update {
        border-color: #BBF7D0;
        background: #F0FDF4;
    }

    .stat-box-label {
        font-size: 0.63rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 6px;
    }

    .stat-box-val {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.8rem;
        color: var(--text);
        line-height: 1;
        letter-spacing: -0.02em;
    }

    .stat-box-val.cost { color: var(--accent); }

    /* Action */
    .action-section {
        padding: 1.5rem 2rem;
    }

    #btn-termina {
        width: 100%;
        padding: 13px;
        background: var(--surface2);
        border: 1px solid var(--red-border);
        border-radius: 12px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--red);
        cursor: pointer;
        transition: all 0.15s;
        letter-spacing: 0.01em;
    }

    #btn-termina:hover {
        background: var(--red-bg);
        border-color: #FCA5A5;
        box-shadow: 0 2px 10px rgba(220,38,38,0.12);
    }
</style>

<div class="session-outer">
    <div class="live-badge">
        <span class="live-pulse"></span>
        Ricarica in corso
    </div>

    <div class="session-card">

        <!-- kWh -->
        <div class="kwh-section">
            <p class="kwh-label">Energia erogata</p>
            <div class="kwh-row">
                <span id="kwh-display" class="kwh-number">0.00</span>
                <span class="kwh-unit">kWh</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-box" id="box-time">
                    <p class="stat-box-label">Durata</p>
                    <p class="stat-box-val" id="time-display">00:00</p>
                </div>
                <div class="stat-box" id="box-xp">
                    <p class="stat-box-label">XP guadagnati</p>
                    <p class="stat-box-val cost" id="xp-display">+5 XP</p>
                </div>
            </div>
        </div>

        <!-- Termina -->
        <div class="action-section">
            <button onclick="terminaRicarica()" id="btn-termina">
                Termina ricarica
            </button>
        </div>

    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
    window.Pusher = Pusher;
    const _isHttps = window.location.protocol === 'https:';
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: '{{ env("VITE_REVERB_APP_KEY") }}',
        wsHost:  window.location.hostname,
        wsPort:  _isHttps ? 443 : 80,
        wssPort: 443,
        forceTLS: _isHttps,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept':       'application/json',
            },
        },
    });

    const sessionUuid    = "{{ $session_uuid }}";
    const idPunto        = "{{ $id_punto }}";
    const idUtente       = "{{ Auth::user()->id_utente }}";
    // XP guadagnati = kWh * 10, minimo 5 garantiti (stessa formula di GamificationService)
    const XP_PER_KWH = 10;

    // ── Durata: ancorata all'inizio REALE della sessione ─────────────────────
    // Il server ci passa data_inizio (dal DB, impostato da sp_avvio_sessione).
    // Cosi' la durata mostrata e' sempre quella reale, anche se la pagina
    // viene aperta a ricarica gia' iniziata o da un altro dispositivo.
    // Se la sessione e' gia' chiusa, il timer si ferma su data_fine.
    const dataInizioMs = {{ $inizio_ms ?? 'Date.now()' }};
    const dataFineMs   = {{ $fine_ms ?? 'null' }};
    // ────────────────────────────────────────────────────────────────────────

    let kwhTotali = {{ $kwh_iniziali ?? 0 }};
    aggiornaUI(kwhTotali);

    window.Echo.private(`user.${idUtente}`)
        .listen('.ricarica.heartbeat', (e) => {
            if (e.id_sessione && e.id_sessione !== sessionUuid) return;
            kwhTotali += Number(e.cambiamento_kwh) || 0;
            aggiornaUI(kwhTotali);
        });

    // Aggiorno la durata ogni secondo partendo dall'inizio reale della
    // sessione. Se la sessione e' chiusa, mostro la durata fissa e non avvio
    // l'intervallo.
    function aggiornaDurata() {
        const riferimento = dataFineMs || Date.now();
        document.getElementById('time-display').innerText = formattaDurata(riferimento - dataInizioMs);
    }
    aggiornaDurata();
    if (!dataFineMs) {
        setInterval(aggiornaDurata, 1000);
    }

    function aggiornaUI(kwh) {
        document.getElementById('kwh-display').innerText = kwh.toFixed(2);
        const xp = Math.max(5, Math.round(kwh * XP_PER_KWH));
        document.getElementById('xp-display').innerText = `+${xp} XP`;
        flashUI();
    }

    function formattaDurata(ms) {
        const sec = Math.floor(ms / 1000);
        const m   = String(Math.floor(sec / 60)).padStart(2, '0');
        const s   = String(sec % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    function flashUI() {
        const els = [
            document.getElementById('kwh-display'),
            document.getElementById('box-time'),
            document.getElementById('box-xp'),
        ];
        els[0].classList.add('flash');
        els[1].classList.add('flash-update');
        els[2].classList.add('flash-update');
        setTimeout(() => {
            els[0].classList.remove('flash');
            els[1].classList.remove('flash-update');
            els[2].classList.remove('flash-update');
        }, 700);
    }

    async function terminaRicarica() {
        if (!confirm('Confermi di voler terminare la ricarica?')) return;
        try {
            const resp = await fetch(`/api/session/${sessionUuid}/stop`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer {{ $api_token }}',
                    'Accept': 'application/json',
                },
            });
            const data = await resp.json();
            if (resp.ok) {
                window.location.href = '/profilo';
            } else {
                alert(data.error || 'Errore durante la chiusura della sessione');
            }
        } catch (err) {
            console.error(err);
            alert('Errore di rete');
        }
    }
</script>
@endpush

@endsection