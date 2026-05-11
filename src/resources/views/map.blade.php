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
    const stazioniMarkersMap = {}; 

    const apiToken = "{{ $api_token }}";
    const centerLat = {{ $center_lat ?? 45.4642 }};
    const centerLng = {{ $center_lng ?? 9.1900 }};
    const zoomLevel = {{ $zoom ?? 14 }};

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

        const canaleMappa = window.Echo.channel('mappa');

        canaleMappa.listen('.stazione.status', (e) => {
            const stazioneMarker = stazioniMarkersMap[e.id_stazione];
            if (stazioneMarker) {
                stazioneMarker.setStyle({
                    fillColor: e.disponibile ? '#22c55e' : '#ef4444'
                });
            }

        });

        // .
    } catch (error) {
        console.error("Errore Echo:", error);

    }

    const map = L.map('map').setView([centerLat, centerLng], zoomLevel);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Funzione per capire se la stazione ha almeno un punto libero
    function getStazioneColor(stazione) {
        if (!stazione.punti_ricarica || stazione.punti_ricarica.length === 0) return '#9ca3af';
        
        // Se c'è almeno un punto con libera == 1, la stazione è verde
        const haPuntiLiberi = stazione.punti_ricarica.some(p => p.libera == 1 && p.stato_hardware === 'online');
        return haPuntiLiberi ? '#22c55e' : '#ef4444';
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
                // CREIAMO UN SOLO MARKER PER STAZIONE
                const marker = L.circleMarker([stazione.latitudine, stazione.longitudine], {
                    radius: 12,
                    fillColor: getStazioneColor(stazione),
                    color: "#fff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);

                stazioniMarkersMap[stazione.id_stazione] = marker;

                const popupContent = `
                    <div class="p-2 text-center">
                        <h3 class="font-bold text-gray-800">${stazione.nome}</h3>
                        <p class="text-[10px] text-gray-400 mb-1">ID STAZIONE: ${stazione.id_stazione}</p>
                        <p class="text-sm mb-3">Prese totali: ${stazione.punti_ricarica.length}</p>
                        
                        <button onclick="goToDetail('${stazione.id_stazione}')" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-blue-700 transition w-full">
                            VAI AL DETTAGLIO
                        </button>
                    </div>
                `;
                marker.bindPopup(popupContent);
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