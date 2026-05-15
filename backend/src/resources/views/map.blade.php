@extends('layouts.app')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap');

    /* ── PAGE HEADER ── */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 1.5rem;
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

    /* ── LEGEND ── */
    .legend {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        padding: 8px 18px;
        box-shadow: var(--shadow-sm);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 0.76rem;
        color: var(--text-2);
        font-weight: 500;
    }

    .legend-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .legend-dot.green { background: #16A34A; }
    .legend-dot.red   { background: #DC2626; }
    .legend-dot.gray  { background: #9CA3AF; }

    .legend-sep {
        width: 1px; height: 14px;
        background: var(--border);
    }

    /* ── MAP WRAPPER ── */
    .map-wrap {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
    }

    #map {
        width: 100%;
        height: 620px;
    }

    /* ── LEAFLET POPUP OVERRIDE ── */
    .leaflet-popup-content-wrapper {
        background: #FFFFFF !important;
        border: 1px solid #E4E2DA !important;
        border-radius: 16px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06) !important;
        padding: 0 !important;
        overflow: hidden;
    }

    .leaflet-popup-content {
        margin: 0 !important;
        width: auto !important;
    }

    .leaflet-popup-tip {
        background: #FFFFFF !important;
        box-shadow: none !important;
    }

    .leaflet-popup-close-button {
        color: #A8A69E !important;
        font-size: 16px !important;
        top: 12px !important;
        right: 14px !important;
        width: 20px !important;
        height: 20px !important;
        line-height: 20px !important;
    }

    .leaflet-popup-close-button:hover { color: #1A1916 !important; }

    /* ── POPUP CONTENT ── */
    .gs-popup {
        min-width: 240px;
        font-family: 'DM Sans', sans-serif;
    }

    .gs-popup-top {
        padding: 16px 18px 14px;
        border-bottom: 1px solid #F2F1ED;
    }

    .gs-popup-eyebrow {
        font-size: 0.63rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #A8A69E;
        margin-bottom: 5px;
    }

    .gs-popup-name {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.1rem;
        color: #1A1916;
        letter-spacing: -0.01em;
        font-weight: 400;
        line-height: 1.2;
    }

    .gs-popup-id {
        font-size: 0.65rem;
        color: #C8C6BE;
        font-family: 'DM Mono', 'Courier New', monospace;
        margin-top: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .gs-popup-bottom {
        padding: 12px 18px 16px;
    }

    .gs-popup-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .gs-popup-prese {
        font-size: 0.8rem;
        color: #6B6860;
        font-weight: 400;
    }

    .gs-popup-prese strong {
        color: #1A1916;
        font-weight: 600;
    }

    .gs-popup-btn {
        display: block;
        width: 100%;
        background: #2A6B4A;
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 11px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        cursor: pointer;
        text-align: center;
        transition: background 0.15s, box-shadow 0.15s;
    }

    .gs-popup-btn:hover {
        background: #1f5238;
        box-shadow: 0 3px 12px rgba(42,107,74,0.3);
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="page-header">
    <div>
        <p class="page-eyebrow">Infrastruttura</p>
        <h1 class="page-title">Stazioni di Ricarica</h1>
    </div>
    <div class="legend">
        <span class="legend-item"><span class="legend-dot green"></span>Libera</span>
        <span class="legend-sep"></span>
        <span class="legend-item"><span class="legend-dot red"></span>Occupata</span>
        <span class="legend-sep"></span>
        <span class="legend-item"><span class="legend-dot gray"></span>Offline</span>
    </div>
</div>

<div class="map-wrap">
    <div id="map"></div>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
    const stazioniMarkersMap = {};

    const apiToken = "{{ $api_token }}";
    const centerLat = {{ $center_lat ?? 45.4642 }};
    const centerLng = {{ $center_lng ?? 9.1900 }};
    const zoomLevel = {{ $zoom ?? 14 }};

    // Cache locale dello stato dei punti per ricalcolare il colore della stazione
    // quando arrivano eventi punto.status / punto.hardware.status (l'API
    // /api/stations e' chiamata solo al caricamento).
    const stazioniDataMap = {};

    function ricoloraStazione(idStazione) {
        const marker   = stazioniMarkersMap[idStazione];
        const stazione = stazioniDataMap[idStazione];
        if (!marker || !stazione) return;
        marker.setStyle({ fillColor: getStazioneColor(stazione) });
    }

    try {
        window.Pusher = Pusher;
        // wsHost / wsPort / forceTLS calcolati dall'host della pagina:
        //   - http://localhost      -> ws://localhost:80/app/{key}     (proxy Apache)
        //   - https://ngrok.app     -> wss://ngrok.app:443/app/{key}   (proxy Apache, TLS tramite ngrok)
        // Cosi' lo stesso codice funziona in dev e in pubblicazione via ngrok.
        const _isHttps = window.location.protocol === 'https:';
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ env("VITE_REVERB_APP_KEY") }}',
            wsHost:  window.location.hostname,
            wsPort:  _isHttps ? 443 : 80,
            wssPort: 443,
            forceTLS: _isHttps,
            enabledTransports: ['ws', 'wss'],
        });

        const canaleMappa = window.Echo.channel('mappa');

        // Stazione intera libera/occupata
        canaleMappa.listen('.stazione.status', (e) => {
            const stazione = stazioniDataMap[e.id_stazione];
            if (stazione) stazione._statoStazione = e.libera;
            ricoloraStazione(e.id_stazione);
        });

        // Cambio stato di un singolo punto (libero/occupato)
        canaleMappa.listen('.punto.status', (e) => {
            const stazione = e.id_stazione ? stazioniDataMap[e.id_stazione] : null;
            if (stazione) {
                const punto = stazione.punti_ricarica.find(p => p.id_punto === e.id_punto);
                if (punto) {
                    punto.libera = e.libera ? 1 : 0;
                    ricoloraStazione(e.id_stazione);
                }
                return;
            }
            // Fallback: cerca il punto in tutte le stazioni
            for (const idStazione in stazioniDataMap) {
                const punto = stazioniDataMap[idStazione].punti_ricarica.find(p => p.id_punto === e.id_punto);
                if (punto) {
                    punto.libera = e.libera ? 1 : 0;
                    ricoloraStazione(idStazione);
                    return;
                }
            }
        });

        // Cambio stato hardware di un punto (online/offline/guasto/manutenzione)
        canaleMappa.listen('.punto.hardware.status', (e) => {
            const stazione = stazioniDataMap[e.id_stazione];
            if (!stazione) return;
            const punto = stazione.punti_ricarica.find(p => p.id_punto === e.id_punto);
            if (punto) {
                punto.stato_hardware = e.stato_hardware;
                ricoloraStazione(e.id_stazione);
            }
        });

    } catch (error) {
        console.error("Errore Echo:", error);
    }

    const map = L.map('map').setView([centerLat, centerLng], zoomLevel);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    function getStazioneColor(stazione) {
        if (!stazione.punti_ricarica || stazione.punti_ricarica.length === 0) return '#9CA3AF';
        const tuttiOffline = stazione.punti_ricarica.every(p => p.stato_hardware !== 'online');
        if (tuttiOffline) return '#9CA3AF';
        const haPuntiLiberi = stazione.punti_ricarica.some(p => p.libera == 1 && p.stato_hardware === 'online');
        return haPuntiLiberi ? '#16A34A' : '#DC2626';
    }

    async function loadStations() {
        try {
            const response = await fetch('/api/stations', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + apiToken,
                    'Accept': 'application/json'
                }
            });

            const jsonResponse = await response.json();
            const stazioni = jsonResponse.data;

            stazioni.forEach(stazione => {
                const color = getStazioneColor(stazione);
                const marker = L.circleMarker([stazione.latitudine, stazione.longitudine], {
                    radius: 11,
                    fillColor: color,
                    color: '#FFFFFF',
                    weight: 2.5,
                    opacity: 1,
                    fillOpacity: 0.9
                }).addTo(map);

                stazioniMarkersMap[stazione.id_stazione] = marker;
                stazioniDataMap[stazione.id_stazione]    = stazione;

                const popupContent = `
                    <div class="gs-popup">
                        <div class="gs-popup-top">
                            <p class="gs-popup-eyebrow">Stazione di ricarica</p>
                            <p class="gs-popup-name">${stazione.nome}</p>
                            <p class="gs-popup-id">${stazione.id_stazione}</p>
                        </div>
                        <div class="gs-popup-bottom">
                            <div class="gs-popup-meta">
                                <span class="gs-popup-prese">Prese totali: <strong>${stazione.punti_ricarica.length}</strong></span>
                            </div>
                            <button onclick="goToDetail('${stazione.id_stazione}')" class="gs-popup-btn">
                                Vai al dettaglio →
                            </button>
                        </div>
                    </div>
                `;
                marker.bindPopup(popupContent, { maxWidth: 380, minWidth: 360 });
            });
        } catch (error) {
            console.error('Errore caricamento stazioni:', error);
        }
    }

    function goToDetail(idStazione) {
        window.location.href = '/stazione/' + idStazione;
    }

    loadStations();
</script>
@endpush
@endsection