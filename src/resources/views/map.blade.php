@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Mappa Stazioni di Ricarica</h1>
        <div class="flex space-x-4 text-sm">
            <span class="flex items-center"><span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span> Libera</span>
            <span class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span> Occupata</span>
            <span class="flex items-center"><span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span> Offline</span>
        </div>
    </div>

    <div id="map" class="w-full h-[600px] rounded-2xl shadow-lg border border-gray-200"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

<script>
    // 1. OGGETTO PER MEMORIZZARE I MARKER (Per Real-time)
    const markersMap = {}; 
    const stazioniMarkersMap = {};

    // --- VARIABILI DI AMBIENTE ---
    const apiToken = "{{ $api_token }}";
    const centerLat = {{ $center_lat ?? 45.4642 }};
    const centerLng = {{ $center_lng ?? 9.1900 }};
    const zoomLevel = {{ $zoom ?? 14 }};

    /**
     * CONFIGURAZIONE REAL-TIME PROTETTA
     */
    try {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ env("VITE_REVERB_APP_KEY") }}',
            wsHost: '{{ env("VITE_REVERB_HOST") }}',
            wsPort: {{ env("VITE_REVERB_PORT", 8080) }},
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
        });

        // Supponendo che il canale si chiami 'mappa'
        const canaleMappa = window.Echo.channel('mappa');

        canaleMappa.listen('.punto.status', (e) => {
            console.log('Punto aggiornato:', e);
            const marker = markersMap[e.id_punto];
            if (marker) {
                marker.setStyle({
                    fillColor: getMarkerColor(e.libera ? 'libera' : 'occupata')
                });
                const statusSpan = document.querySelector(`.status-text-${e.id_punto}`);
                if (statusSpan) statusSpan.innerText = e.libera ? 'Libero' : 'Occupato';
            }
        });

        canaleMappa.listen('.stazione.status', (e) => {
            console.log('Stazione aggiornata:', e);
            const stazioneMarker = stazioniMarkersMap[e.id_stazione];
            if (stazioneMarker) {
                stazioneMarker.setStyle({
                    fillColor: e.disponibile ? '#22c55e' : '#ef4444'
                });
            }
        });
            
        console.log("Sistema Real-time inizializzato.");
    } catch (error) {
        console.error("Errore Echo: la mappa funzionerà ma non si aggiornerà da sola.", error);
    }

    // --- INIZIALIZZAZIONE MAPPA ---
    const map = L.map('map').setView([centerLat, centerLng], zoomLevel);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    function getMarkerColor(stato) {
        if(!stato) return '#9ca3af';
        const s = stato.toLowerCase();
        if (s === 'libera' || s === 'online') return '#22c55e';
        if (s === 'occupata') return '#ef4444';
        return '#9ca3af';
    }

    /**
     * FUNZIONE CARICAMENTO STAZIONI
     */
    async function loadStations() {
        try {
            const response = await fetch('/api/stations', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + apiToken,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Errore API');

            const jsonResponse = await response.json();
            const stazioni = jsonResponse.data;

            stazioni.forEach(stazione => {
                if (stazione.punti_ricarica && stazione.punti_ricarica.length > 0) {
                    stazione.punti_ricarica.forEach(punto => {
                        // Creazione marker
                        const marker = L.circleMarker([stazione.latitudine, stazione.longitudine], {
                            radius: 10,
                            fillColor: getMarkerColor(punto.stato || punto.stato_hardware),
                            color: "#fff",
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.8
                        }).addTo(map);

                        // Salvataggio nell'indice per il real-time
                        markersMap[punto.id_punto] = marker;
                        stazioniMarkersMap[stazione.id_stazione] = marker;

                        // Content del Popup (Modifiche compagno)
                        const popupContent = `
                            <div class="p-2 text-center">
                                <h3 class="font-bold text-gray-800">${stazione.nome}</h3>
                                <p class="text-xs text-gray-500 mb-1">Codice: ${punto.id_punto}</p>
                                <p class="text-sm mb-3">Stato: <span class="status-text-${punto.id_punto} font-semibold">${punto.stato || 'N/D'}</span></p>
                                <button onclick="startSimulatedCharge('${punto.id_punto}')" 
                                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-green-700 transition w-full mb-2">
                                    AVVIA RICARICA SIMULATA
                                </button>
                                <button onclick="goToDetail('${stazione.id_stazione}')" 
                                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-blue-700 transition w-full">
                                    VAI AL DETTAGLIO
                                </button>
                            </div>
                        `;
                        marker.bindPopup(popupContent);

                        // Manteniamo la tua funzione originale: click sul pallino = vai al dettaglio
                        marker.on('click', function(e) {
                            // Se vuoi che il click apra solo il popup invece di sloggare subito alla pagina, 
                            // commenta la riga sotto. Se vuoi entrambi, lasciala.
                            // window.location.href = '/stazione/' + stazione.id_stazione;
                        });
                    });
                }
            });
        } catch (error) {
            console.error('Errore caricamento stazioni:', error);
        }
    }

    // Funzione per il redirect (tua vecchia logica)
    function goToDetail(idStazione) {
        window.location.href = '/stazione/' + idStazione;
    }

    function startSimulatedCharge(idPunto) {
        const mockUuid = 'sessione-' + Math.random().toString(36).substr(2, 9);
        window.location.href = `/session/${mockUuid}`;
    }

    // Eseguiamo il caricamento
    loadStations();
</script>
@endpush
@endsection