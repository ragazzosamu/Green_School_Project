@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-800">Mappa Stazioni</h1>
        <div id="status-legend" class="flex space-x-4 text-sm">
            <span class="flex items-center"><span class="w-4 h-4 bg-green-500 rounded-full mr-2 border-2 border-white shadow-sm"></span> Libera</span>
            <span class="flex items-center"><span class="w-4 h-4 bg-red-500 rounded-full mr-2 border-2 border-white shadow-sm"></span> Occupata</span>
        </div>
    </div>

    <div id="map" class="w-full h-[600px] rounded-3xl shadow-lg border-4 border-white overflow-hidden z-0"></div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Recupero il token passato dal WebAuthController
        const apiToken = "{{ session('api_token') }}";

        // Inizializzo mappa su Milano (cambia coordinate se serve)
        var map = L.map('map').setView([45.4642, 9.1900], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        async function fetchStations() {
            try {
                // Chiamata API protetta da Sanctum
                const response = await fetch('/api/stations', {
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + apiToken,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error("Accesso negato alle API");

                const stations = await response.json();

                stations.forEach(station => {
                    let color = station.stato === 'libera' ? '#22c55e' : '#ef4444';
                    
                    let customIcon = L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="background-color: ${color};" class="w-6 h-6 rounded-full border-2 border-white shadow-md"></div>`,
                        iconSize: [24, 24]
                    });

                    L.marker([station.latitudine, station.longitudine], {icon: customIcon})
                        .addTo(map)
                        .bindPopup(`
                            <div class="p-2 text-center">
                                <b class="text-lg">${station.nome}</b><br>
                                <span class="text-sm text-gray-500">Stato: ${station.stato}</span><br>
                                <button onclick="startDemo('${station.id}')" class="mt-2 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-bold">
                                    Avvia Ricarica
                                </button>
                            </div>
                        `);
                });
            } catch (error) {
                console.error("Errore:", error);
            }
        }

        window.startDemo = function(id) {
            window.location.href = '/session-active';
        };

        if(apiToken) fetchStations();
    });
</script>
@endpush