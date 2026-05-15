@extends('layouts.app')

@section('content')

<style>
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
        --red:       #DC2626;
        --red-bg:    #FEF2F2;
        --red-border:#FECACA;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
    }

    /* ── BACK ── */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-3);
        text-decoration: none;
        margin-bottom: 1.5rem;
        transition: color 0.15s;
    }
    .back-link:hover { color: var(--text-2); }

    /* ── MAIN CARD ── */
    .station-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
    }

    /* ── HEADER — stack verticale su mobile ── */
    .station-header {
        padding: 1.75rem 2rem;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
    }

    /* Nome + indirizzo */
    .station-meta { margin-bottom: 1.25rem; }

    .station-eyebrow {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 5px;
    }

    .station-name {
        font-family: 'Instrument Serif', Georgia, serif;
        font-size: clamp(1.4rem, 4vw, 1.9rem);
        color: var(--text);
        letter-spacing: -0.03em;
        line-height: 1.15;
        margin-bottom: 4px;
        /* Niente truncate — va a capo */
        word-break: break-word;
    }

    .station-address {
        font-size: 0.82rem;
        color: var(--text-3);
    }

    /* Chips statistiche — sempre in riga orizzontale */
    .station-chips {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;   /* NON vanno a capo, restano su una riga */
    }

    .stat-chip {
        flex: 1;             /* occupano lo spazio disponibile in ugual misura */
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px 12px;
        text-align: center;
        min-width: 0;        /* evita overflow */
    }

    .stat-chip-val {
        font-family: 'Instrument Serif', Georgia, serif;
        font-size: 1.5rem;
        color: var(--text);
        line-height: 1;
    }

    .stat-chip-val.green { color: #16A34A; }
    .stat-chip-val.red   { color: var(--red); }

    .stat-chip-label {
        font-size: 0.62rem;
        color: var(--text-3);
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        margin-top: 2px;
        white-space: nowrap;
    }

    /* ── QR SCANNER OVERLAY ── */
    #qr-reader-container {
        position: fixed;
        inset: 0;
        background: rgba(26,25,22,0.6);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        animation: overlayIn 0.2s ease both;
    }

    #qr-reader-container.hidden { display: none; }

    @keyframes overlayIn {
        from { opacity:0; }
        to   { opacity:1; }
    }

    .qr-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 1.75rem;
        width: 100%;
        max-width: 390px;
        box-shadow: 0 24px 64px rgba(0,0,0,0.22), 0 4px 16px rgba(0,0,0,0.1);
        animation: qrCardIn 0.3s cubic-bezier(0.16,1,0.3,1) both;
    }

    @keyframes qrCardIn {
        from { opacity:0; transform: scale(0.95) translateY(10px); }
        to   { opacity:1; transform: scale(1) translateY(0); }
    }

    .qr-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1.25rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .qr-icon-box {
        width: 40px; height: 40px;
        background: var(--accent-bg);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .qr-title {
        font-family: 'Geist', sans-serif;
        font-weight: 600;
        font-size: 0.92rem;
        color: var(--text);
        line-height: 1.2;
    }

    .qr-subtitle {
        font-size: 0.76rem;
        color: var(--text-3);
        margin-top: 2px;
    }

    .qr-camera-wrap {
        border-radius: 12px;
        overflow: hidden;
        background: #F2F1ED;
        border: 1px solid var(--border);
        position: relative;
        margin-bottom: 1rem;
    }

    #reader { width: 100% !important; min-height: 220px; }

    /* Angoli scanner */
    .scan-corner {
        position: absolute;
        width: 32px; height: 32px;
        border-color: var(--accent);
        border-style: solid;
        border-width: 0;
        z-index: 5;
        pointer-events: none;
    }
    .sc-tl { top:12px; left:12px; border-top-width:3px; border-left-width:3px; border-radius:3px 0 0 0; }
    .sc-tr { top:12px; right:12px; border-top-width:3px; border-right-width:3px; border-radius:0 3px 0 0; }
    .sc-bl { bottom:12px; left:12px; border-bottom-width:3px; border-left-width:3px; border-radius:0 0 0 3px; }
    .sc-br { bottom:12px; right:12px; border-bottom-width:3px; border-right-width:3px; border-radius:0 0 3px 0; }

    .scan-line {
        position: absolute;
        left: 14px; right: 14px;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        border-radius: 1px;
        animation: scanAnim 2s ease-in-out infinite;
        z-index: 6;
        pointer-events: none;
    }

    @keyframes scanAnim {
        0%   { top:15%; opacity:0; }
        8%   { opacity:1; }
        92%  { opacity:1; }
        100% { top:85%; opacity:0; }
    }

    .qr-cancel-btn {
        width: 100%;
        padding: 12px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-family: 'Geist', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--text-2);
        cursor: pointer;
        transition: all 0.15s;
    }
    .qr-cancel-btn:hover { background: #E8E6E0; color: var(--text); }

    /* ── PRESE ── */
    .prese-section { padding: 1.75rem 2rem; }

    .prese-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 0.9rem;
    }

    @media (max-width: 600px) {
        .prese-section { padding: 1.25rem 1rem; }
        .station-header { padding: 1.25rem 1rem; }
        .prese-grid { grid-template-columns: 1fr; }
    }

    .presa-card {
        border-radius: 14px;
        border: 1px solid var(--border);
        padding: 1.1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        transition: box-shadow 0.15s;
        background: var(--surface);
    }

    .presa-card:hover { box-shadow: var(--shadow-sm); }
    .presa-card.stato-libera   { border-color: #BBF7D0; background: #F0FDF4; }
    .presa-card.stato-occupata { border-color: var(--red-border); background: var(--red-bg); }
    .presa-card.stato-offline  { background: var(--surface2); opacity: 0.6; }

    .status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:2px; }
    .status-dot.green { background:#16A34A; }
    .status-dot.red   { background:var(--red); }
    .status-dot.gray  { background:#D1D5DB; }

    .presa-info { flex:1; min-width:0; }

    .presa-name-row {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 3px;
        flex-wrap: wrap;
    }

    .presa-name {
        font-family: 'Geist', sans-serif;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text);
        letter-spacing: 0.02em;
    }

    .presa-badge {
        font-size: 0.6rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 2px 8px;
        border-radius: 100px;
        flex-shrink: 0;
    }

    .presa-badge.libera   { color:#15803D; background:#DCFCE7; }
    .presa-badge.occupata { color:#B91C1C; background:#FEE2E2; }
    .presa-badge.offline  { color:#9CA3AF; background:#F3F4F6; }

    .presa-power { font-size:0.78rem; color:var(--text-3); margin-bottom:2px; }
    .presa-id { font-size:0.58rem; color:#D1D0CA; font-family:monospace; word-break:break-all; }

    .scegli-btn {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 16px;
        font-family: 'Geist', sans-serif;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        transition: background 0.15s, box-shadow 0.15s;
        box-shadow: 0 1px 6px rgba(42,107,74,0.2);
    }
    .scegli-btn:hover { background:#1f5238; box-shadow:0 3px 12px rgba(42,107,74,0.3); }
</style>

<a href="{{ route('map') }}" class="back-link">← Torna alla mappa</a>

<div class="station-card">

    <!-- Header: prima il nome, poi i chip sotto -->
    <div class="station-header">
        <div class="station-meta">
            <p class="station-eyebrow">Dettaglio stazione</p>
            <h1 class="station-name">{{ $stazione->nome }}</h1>
            <p class="station-address">{{ $stazione->indirizzo }}</p>
        </div>

        <div class="station-chips">
            <div class="stat-chip">
                <div class="stat-chip-val">{{ $stazione->puntiRicarica->count() }}</div>
                <div class="stat-chip-label">Totale</div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-val green">{{ $stazione->puntiRicarica->where('stato_hardware','online')->where('libera',1)->count() }}</div>
                <div class="stat-chip-label">Libere</div>
            </div>
            <div class="stat-chip">
                <div class="stat-chip-val red">{{ $stazione->puntiRicarica->where('stato_hardware','online')->where('libera',0)->count() }}</div>
                <div class="stat-chip-label">In uso</div>
            </div>
        </div>
    </div>

    <!-- Scanner QR -->
    <div id="qr-reader-container" class="hidden">
        <div class="qr-card">
            <div class="qr-header">
                <div class="qr-icon-box">📷</div>
                <div>
                    <p class="qr-title">Scansiona il QR Code</p>
                    <p class="qr-subtitle">Inquadra il codice sulla presa selezionata</p>
                </div>
            </div>

            <div class="qr-camera-wrap">
                <div class="scan-corner sc-tl"></div>
                <div class="scan-corner sc-tr"></div>
                <div class="scan-corner sc-bl"></div>
                <div class="scan-corner sc-br"></div>
                <div class="scan-line"></div>
                <div id="reader"></div>
            </div>

            <button onclick="chiudiScanner()" class="qr-cancel-btn">Annulla</button>
        </div>
    </div>

    <!-- Prese -->
    <div class="prese-section">
        <div class="prese-grid">
            @foreach($stazione->puntiRicarica as $punto)
                @php
                    $isOnline = $punto->stato_hardware === 'online';
                    $isLibera = $punto->libera == 1;

                    if (!$isOnline) {
                        $cardClass  = 'stato-offline';
                        $dotClass   = 'gray';
                        $badgeClass = 'offline';
                        $badgeLabel = 'Offline';
                    } elseif ($isLibera) {
                        $cardClass  = 'stato-libera';
                        $dotClass   = 'green';
                        $badgeClass = 'libera';
                        $badgeLabel = 'Disponibile';
                    } else {
                        $cardClass  = 'stato-occupata';
                        $dotClass   = 'red';
                        $badgeClass = 'occupata';
                        $badgeLabel = 'In uso';
                    }
                @endphp

                <div class="presa-card {{ $cardClass }}">
                    <div class="presa-info">
                        <div class="presa-name-row">
                            <span class="status-dot {{ $dotClass }}"></span>
                            <span class="presa-name">{{ $punto->identificativo_fisico }}</span>
                            <span class="presa-badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        </div>
                        <p class="presa-power">{{ $punto->potenza_max_kw }} kW</p>
                        <p class="presa-id">{{ $punto->id_punto }}</p>
                    </div>

                    @if($isOnline && $isLibera)
                        <button onclick="apriScanner('{{ $punto->id_punto }}')" class="scegli-btn">
                            Scegli →
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

</div>

<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    let html5QrCode;
    let puntoCorrente = null;
    //ascoltare su punto.idpunto 
    //su mappa ascolto il punto.status per vedere se la stazione è libera

    //se

    function apriScanner(idPunto) {
        puntoCorrente = idPunto;
        document.getElementById('qr-reader-container').classList.remove('hidden');
        
        html5QrCode = new Html5Qrcode("reader");
        
        // TODO EVENTO WEBSOCKET
        html5QrCode.start(
            { facingMode: "environment" }, 
            { fps: 10, qrbox: { width: 200, height: 200 } },
            (decodedText) => {
                const parts = decodedText.split(':');
                const idStazionePagina = "{{ $stazione->id_stazione }}";

                if(parts[0] === 'gs' && parts[1] === idStazionePagina) {
                    inviaDati(puntoCorrente, parts[2], idStazionePagina); 
                    chiudiScanner();
                } else {
                    alert("Questo QR non corrisponde a questa stazione!");
                }
            },
            (errorMessage) => { }
        ).catch(err => {
            console.error("Errore Camera:", err);
            chiudiScanner();
        });
    }

    function chiudiScanner() {
        if(html5QrCode) {
            html5QrCode.stop().then(() => {
                document.getElementById('qr-reader-container').classList.add('hidden');
            }).catch(err => {
                document.getElementById('qr-reader-container').classList.add('hidden');
            });
        } else {
            document.getElementById('qr-reader-container').classList.add('hidden');
        }
    }

    async function inviaDati(idPunto, firma, idStazione) {
        try {
            const response = await fetch('/api/scan-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer {{ session("api_token") }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id_punto: idPunto,
                    id_stazione: idStazione,
                    firma: firma
                })
            });

            const data = await response.json();

            // 202 = QR validato, in attesa che l'utente colleghi il cavo.
            // Niente overlay locale: redirigiamo subito alla pagina personale,
            // che fa polling sulla sessione attiva dell'utente.
            if (response.status === 202) {
                window.location.href = '/profilo?attesa=' + encodeURIComponent(idPunto);
                return;
            }

            // Compatibilita': se l'API rispondesse subito con un session_uuid.
            if (response.ok && data.session_uuid) {
                window.location.href = '/session/' + data.session_uuid;
                return;
            }

            console.error("Dettaglio Errore:", data);
            alert("ERRORE: " + (data.message || data.error || JSON.stringify(data)));
        } catch (error) {
            console.error("Errore di rete:", error);
            alert("Errore di rete: controlla la console.");
        }
    }
</script>
@endsection