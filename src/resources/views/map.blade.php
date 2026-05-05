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

<script>
    // Recuperiamo i dati passati dal server Laravel (PHP) e li trasformiamo in variabili JavaScript
    //QUESTI ERRORI SONO SOLO VISIVI, NON TOCCARE O PROVARE A SISTEMARE
    // @ts-ignore
    const apiToken = "{{ $api_token }}"; // Il token segreto per chiamare le API
    // @ts-ignore
    const centerLat = {{ $center_lat ?? 45.4642 }}; // Latitudine centrale
    // @ts-ignore
    const centerLng = {{ $center_lng ?? 9.1900 }}; // Longitudine centrale
    // @ts-ignore
    const zoomLevel = {{ $zoom ?? 14 }}; // Livello di zoom

    // Inizializziamo la mappa Leaflet puntandola sulle coordinate scelte
    const map = L.map('map').setView([centerLat, centerLng], zoomLevel);

    // Aggiungiamo i tasselli (tiles) di OpenStreetMap per vedere le strade
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    /**
     * Funzione che assegna il colore al pallino in base allo stato nel Database
     */
    function getMarkerColor(stato) {
        if(!stato) return '#9ca3af'; // Se non c'è stato -> Grigio
        switch(stato.toLowerCase()) {
            case 'libera': return '#22c55e'; // Verde
            case 'occupata': return '#ef4444'; // Rosso
            default: return '#9ca3af'; // Tutto il resto -> Grigio
        }
    }

    /**
     * Funzione asincrona che scarica i dati delle stazioni dal server
     */
    async function loadStations() {
        try {
            // Chiamata API protetta: inviamo il Token nell'Header Authorization
            const response = await fetch('/api/stations', {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + apiToken,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Errore API');

            const jsonResponse = await response.json();
            
            // Prendiamo l'elenco delle stazioni dentro l'oggetto 'data'
            const stazioni = jsonResponse.data;

            stazioni.forEach(stazione => {
                // Per ogni stazione, controlliamo se ha dei punti di ricarica
                if (stazione.punti_ricarica && stazione.punti_ricarica.length > 0) {
                    
                    stazione.punti_ricarica.forEach(punto => {
                        // Creiamo un cerchietto (marker) sulla mappa per ogni punto trovato
                        const marker = L.circleMarker([stazione.latitudine, stazione.longitudine], {
                            radius: 10,
                            fillColor: getMarkerColor(punto.stato), // Colore dinamico
                            color: "#fff", // Bordo bianco
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.8
                        }).addTo(map);

                        // Contenuto del fumetto che appare cliccando sul pallino
                        const popupContent = `
                            <div class="p-2 text-center">
                                <h3 class="font-bold text-gray-800">${stazione.nome}</h3>
                                <p class="text-xs text-gray-500 mb-1">Codice: ${punto.id_punto}</p>
                                <p class="text-sm mb-3">Stato: <span class="font-semibold">${punto.stato}</span></p>
                                <button onclick="startSimulatedCharge('${punto.id_punto}')" 
                                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-green-700 transition w-full">
                                    AVVIA RICARICA SIMULATA
                                </button>
                            </div>
                        `;
                        marker.bindPopup(popupContent);
                    });
                }
            });

        } catch (error) {
            console.error('Errore:', error);
            alert('Errore nel caricamento dati. Controlla la console.');
        }
    }

    // Funzione di test attivata dal pulsante nel popup
    function startSimulatedCharge(idPunto) {
        alert('Avvio ricarica per il punto: ' + idPunto);
    }

    // Avviamo il caricamento dati appena la pagina è pronta
    loadStations();
</script>
@endpush
@endsection