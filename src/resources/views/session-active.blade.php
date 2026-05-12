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

    /* Progress */
    .progress-track {
        height: 6px;
        background: #E8E6E0;
        border-radius: 100px;
        overflow: visible;
        position: relative;
    }

    #progress-bar {
        height: 100%;
        border-radius: 100px;
        background: linear-gradient(90deg, #16A34A, #22C55E);
        transition: width 0.9s cubic-bezier(0.25,1,0.5,1);
        position: relative;
        min-width: 0;
    }

    #progress-bar::after {
        content: '';
        position: absolute;
        right: -4px; top: 50%;
        transform: translateY(-50%);
        width: 12px; height: 12px;
        border-radius: 50%;
        background: #16A34A;
        border: 2px solid white;
        box-shadow: 0 1px 4px rgba(22,163,74,0.4);
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
            <div class="progress-track">
                <div id="progress-bar" style="width: 0%"></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-box" id="box-time">
                    <p class="stat-box-label">Durata</p>
                    <p class="stat-box-val" id="time-display">00:00</p>
                </div>
                <div class="stat-box" id="box-cost">
                    <p class="stat-box-label">Costo</p>
                    <p class="stat-box-val cost" id="cost-display">€ 0.00</p>
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
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

<script>
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: '{{ env("VITE_REVERB_APP_KEY") }}',
        wsHost: '{{ env("VITE_REVERB_HOST") }}',
        wsPort: {{ env("VITE_REVERB_PORT", 8080) }},
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
    });

    const sessionUuid = "{{ $session_uuid }}";

    window.Echo.private(`session.${sessionUuid}`)
        .listen('SessioneAggiornata', (e) => {
            document.getElementById('kwh-display').innerText = e.kwh_erogati.toFixed(2);
            document.getElementById('time-display').innerText = e.durata;
            document.getElementById('cost-display').innerText = `€ ${e.costo_parziale.toFixed(2)}`;

            let per = (e.kwh_erogati / 50) * 100;
            document.getElementById('progress-bar').style.width = per + '%';

            const els = [
                document.getElementById('kwh-display'),
                document.getElementById('box-time'),
                document.getElementById('box-cost'),
            ];
            els[0].classList.add('flash');
            els[1].classList.add('flash-update');
            els[2].classList.add('flash-update');
            setTimeout(() => {
                els[0].classList.remove('flash');
                els[1].classList.remove('flash-update');
                els[2].classList.remove('flash-update');
            }, 700);
        });
</script>
@endpush
@endsection