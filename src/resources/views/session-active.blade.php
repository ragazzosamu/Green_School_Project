@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto mt-10">
    <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100 text-center">
        
        <h2 class="text-sm font-bold text-green-500 uppercase tracking-widest mb-2">Ricarica in Corso</h2>
        
        <div class="mb-4">
            <span id="kwh-display" class="text-7xl font-black text-gray-800">0.00</span>
            <span class="text-xl font-bold text-gray-400">kWh</span>
        </div>

        <div class="w-full bg-gray-100 rounded-full h-3 mb-8 overflow-hidden">
            <div id="progress-bar" class="bg-green-500 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-gray-50 p-4 rounded-2xl">
                <p class="text-xs font-bold text-gray-400 uppercase">Durata</p>
                <p id="time-display" class="text-xl font-bold text-gray-700">00:00</p>
            </div>
            <div class="bg-gray-50 p-4 rounded-2xl">
                <p class="text-xs font-bold text-gray-400 uppercase">Costo</p>
                <p id="cost-display" class="text-xl font-bold text-gray-700">€ 0.00</p>
            </div>
        </div>

        <<button onclick="terminaRicarica()" id="btn-termina" class="w-full bg-red-500 text-white font-bold py-4 rounded-2xl shadow-lg hover:bg-red-600 transition">
    TERMINA RICARICA
</button>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pusher/8.3.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

<script>
    // Configurazione Echo (Presa dalle direttive del tuo PM)
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

    // ASCOLTO SUL CANALE PRIVATO (Punto 2.9)
    window.Echo.private(`session.${sessionUuid}`)
        .listen('SessioneAggiornata', (e) => {
            // Aggiorniamo i numeri sullo schermo in tempo reale
            document.getElementById('kwh-display').innerText = e.kwh_erogati.toFixed(2);
            document.getElementById('time-display').innerText = e.durata;
            document.getElementById('cost-display').innerText = `€ ${e.costo_parziale.toFixed(2)}`;
            
            // Calcolo percentuale barra (esempio su 50kWh)
            let per = (e.kwh_erogati / 50) * 100;
            document.getElementById('progress-bar').style.width = per + '%';
        });
</script>
@endpush
@endsection