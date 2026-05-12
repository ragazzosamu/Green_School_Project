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
        margin-bottom: 1.75rem;
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

    /* ── HEADER STRIP ── */
    .station-header {
        padding: 2rem 2.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
        background: var(--surface);
    }

    .station-meta { flex: 1; min-width: 0; }

    .station-eyebrow {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 6px;
    }

    .station-name {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.9rem;
        color: var(--text);
        letter-spacing: -0.03em;
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .station-address {
        font-size: 0.85rem;
        color: var(--text-3);
        font-weight: 300;
    }

    /* Stats chips */
    .station-chips {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .stat-chip {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px 16px;
        text-align: center;
        min-width: 72px;
    }

    .stat-chip-val {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.6rem;
        color: var(--text);
        line-height: 1;
        font-weight: 400;
    }

    .stat-chip-val.green { color: #16A34A; }
    .stat-chip-val.red   { color: var(--red); }

    .stat-chip-label {
        font-size: 0.63rem;
        color: var(--text-3);
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-top: 2px;
    }

    /* ── PRESE GRID ── */
    .prese-section {
        padding: 2rem 2.5rem;
    }

    .prese-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    /* ── SINGLE PRESA ── */
    .presa-card {
        border-radius: 14px;
        border: 1px solid var(--border);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        transition: box-shadow 0.15s, border-color 0.15s;
        background: var(--surface);
    }

    .presa-card:hover { box-shadow: var(--shadow-sm); }

    .presa-card.stato-libera {
        border-color: #BBF7D0;
        background: #F0FDF4;
    }

    .presa-card.stato-occupata {
        border-color: var(--red-border);
        background: var(--red-bg);
    }

    .presa-card.stato-offline {
        background: var(--surface2);
        opacity: 0.65;
    }

    .status-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .status-dot.green { background: #16A34A; }
    .status-dot.red   { background: var(--red); }
    .status-dot.gray  { background: #D1D5DB; }

    .presa-info { flex: 1; min-width: 0; }

    .presa-name-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    .presa-name {
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text);
        letter-spacing: 0.02em;
    }

    .presa-badge {
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 2px 8px;
        border-radius: 100px;
        flex-shrink: 0;
    }

    .presa-badge.libera   { color: #15803D; background: #DCFCE7; }
    .presa-badge.occupata { color: #B91C1C; background: #FEE2E2; }
    .presa-badge.offline  { color: #9CA3AF; background: #F3F4F6; }

    .presa-power {
        font-size: 0.8rem;
        color: var(--text-3);
        margin-bottom: 3px;
        font-weight: 300;
    }

    /* ID ingrandito e leggibile */
    .presa-id {
        font-size: 0.72rem;
        color: #B0AEA8;
        font-family: 'DM Mono', 'Courier New', monospace;
        word-break: break-all;
        line-height: 1.4;
        letter-spacing: 0.01em;
    }

    /* Scegli button */
    .scegli-btn {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 10px 18px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s, box-shadow 0.15s;
        box-shadow: 0 1px 6px rgba(42,107,74,0.2);
        flex-shrink: 0;
    }
    .scegli-btn:hover {
        background: #1f5238;
        box-shadow: 0 3px 12px rgba(42,107,74,0.3);
    }

    /* ── QR SCANNER OVERLAY ── */
    #qr-reader-container {
        position: fixed;
        inset: 0;
        background: rgba(15, 15, 12, 0.75);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        animation: overlayIn 0.2s ease both;
    }

    #qr-reader-container.hidden { display: none; }

    @keyframes overlayIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    .qr-card {
        background: #1A1916;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 24px;
        padding: 1.75rem;
        width: 100%;
        max-width: 380px;
        box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 4px 20px rgba(0,0,0,0.3);
        animation: qrCardIn 0.35s cubic-bezier(0.16,1,0.3,1) both;
    }

    @keyframes qrCardIn {
        from { opacity: 0; transform: scale(0.94) translateY(12px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .qr-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1.25rem;
    }

    .qr-icon-box {
        width: 38px; height: 38px;
        background: rgba(42,107,74,0.3);
        border: 1px solid rgba(42,107,74,0.4);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex-shrink: 0;
    }

    .qr-title {
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.92rem;
        color: rgba(255,255,255,0.9);
        line-height: 1.2;
    }

    .qr-subtitle {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.35);
        margin-top: 2px;
        font-weight: 300;
    }

    /* Camera area */
    .qr-camera-wrap {
        border-radius: 16px;
        overflow: hidden;
        background: #0D0D0B;
        border: 1px solid rgba(255,255,255,0.06);
        position: relative;
        margin-bottom: 1rem;
    }

    #reader {
        width: 100% !important;
        min-height: 260px;
    }

    /* Hide html5-qrcode default UI clutter */
    #reader__scan_region img { display: none !important; }
    #reader__dashboard { display: none !important; }

    /* Corner brackets */
    .scan-frame {
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 10;
    }

    .scan-frame::before,
    .scan-frame::after,
    .scan-frame .corner-br,
    .scan-frame .corner-tl-h {
        content: '';
        position: absolute;
        width: 36px; height: 36px;
        border-color: #4ADE80;
        border-style: solid;
        border-width: 0;
    }

    /* top-left */
    .scan-frame::before {
        top: 20px; left: 20px;
        border-top-width: 2.5px;
        border-left-width: 2.5px;
        border-radius: 4px 0 0 0;
    }

    /* top-right */
    .scan-frame::after {
        top: 20px; right: 20px;
        border-top-width: 2.5px;
        border-right-width: 2.5px;
        border-radius: 0 4px 0 0;
    }

    /* bottom-left */
    .corner-bl {
        position: absolute;
        bottom: 20px; left: 20px;
        width: 36px; height: 36px;
        border-bottom: 2.5px solid #4ADE80;
        border-left: 2.5px solid #4ADE80;
        border-radius: 0 0 0 4px;
    }

    /* bottom-right */
    .corner-br {
        position: absolute;
        bottom: 20px; right: 20px;
        width: 36px; height: 36px;
        border-bottom: 2.5px solid #4ADE80;
        border-right: 2.5px solid #4ADE80;
        border-radius: 0 0 4px 0;
    }

    /* Scan line */
    .scan-line {
        position: absolute;
        left: 20px; right: 20px;
        height: 1.5px;
        background: linear-gradient(90deg, transparent, #4ADE80, transparent);
        animation: scanAnim 2.2s ease-in-out infinite;
        z-index: 11;
        pointer-events: none;
    }

    @keyframes scanAnim {
        0%   { top: 18%; opacity: 0; }
        8%   { opacity: 1; }
        92%  { opacity: 1; }
        100% { top: 82%; opacity: 0; }
    }

    /* scanning hint */
    .qr-hint {
        text-align: center;
        font-size: 0.72rem;
        color: rgba(255,255,255,0.3);
        margin-bottom: 1rem;
        font-weight: 300;
        letter-spacing: 0.02em;
    }

    .qr-cancel-btn {
        width: 100%;
        padding: 12px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        color: rgba(255,255,255,0.45);
        cursor: pointer;
        transition: all 0.15s;
    }
    .qr-cancel-btn:hover {
        background: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.7);
    }
</style>

<a href="{{ route('map') }}" class="back-link">← Torna alla mappa</a>

<div class="station-card">

    <!-- Header -->
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
                <div class="scan-frame">
                    <span class="corner-bl"></span>
                    <span class="corner-br"></span>
                </div>
                <div class="scan-line"></div>
                <div id="reader"></div>
            </div>

            <p class="qr-hint">Tieni il telefono fermo sopra il QR</p>

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
                        $cardClass   = 'stato-offline';
                        $dotClass    = 'gray';
                        $badgeClass  = 'offline';
                        $badgeLabel  = 'Offline';
                    } elseif ($isLibera) {
                        $cardClass   = 'stato-libera';
                        $dotClass    = 'green';
                        $badgeClass  = 'libera';
                        $badgeLabel  = 'Disponibile';
                    } else {
                        $cardClass   = 'stato-occupata';
                        $dotClass    = 'red';
                        $badgeClass  = 'occupata';
                        $badgeLabel  = 'In uso';
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

    function apriScanner(idPunto) {
        puntoCorrente = idPunto;
        document.getElementById('qr-reader-container').classList.remove('hidden');

        html5QrCode = new Html5Qrcode("reader");

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 220, height: 220 } },
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

            if (response.ok) {
                window.location.href = '/session/' + data.session_uuid;
            } else {
                console.error("Dettaglio Errore:", data);
                alert("ERRORE SERVER: " + (data.message || data.error || JSON.stringify(data)));
            }
        } catch (error) {
            console.error("Errore di rete:", error);
            alert("Errore di rete: controlla la console.");
        }
    }
</script>
@endsection