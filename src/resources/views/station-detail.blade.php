@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('map') }}" class="text-green-600 font-bold flex items-center gap-2">
            ← Torna alla mappa
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-gray-800">{{ $stazione->nome }}</h1>
            <p class="text-gray-500">{{ $stazione->indirizzo }}</p>
        </div>

        <div id="qr-reader-container" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 flex flex-col items-center justify-center p-4">
            <div class="bg-white rounded-3xl p-6 w-full max-w-md text-center">
                <h2 class="text-xl font-bold mb-4">Inquadra il QR sulla presa</h2>
                <div id="reader" style="width: 100%; min-height: 250px; background: #f3f4f6;"></div>
                <button onclick="chiudiScanner()" class="mt-6 w-full py-3 bg-red-100 text-red-600 rounded-xl font-bold uppercase text-xs">Annulla</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Usiamo la relazione corretta: puntiRicarica --}}
            @foreach($stazione->puntiRicarica->take(2) as $punto)
                <div class="border-2 rounded-[2rem] p-6 flex justify-between items-center transition-all 
                    {{ $punto->stato_hardware === 'online' ? 'border-green-100 bg-green-50' : 'bg-gray-100 opacity-60 border-transparent' }}">
                    
                    <div class="space-y-1 text-left">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $punto->stato_hardware === 'online' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                            <p class="font-black text-gray-800 text-xl uppercase">
                                {{ $punto->identificativo_fisico }}
                            </p>
                        </div>
                        <p class="text-sm text-gray-500 font-medium">Potenza: {{ $punto->potenza_max_kw }} kW</p>
                    </div>

                    @if($punto->stato_hardware === 'online')
                        <button onclick="apriScanner('{{ $punto->id_punto }}')" 
                                class="bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-sm hover:bg-green-600 transition-all shadow-lg">
                            SCEGLI
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
        
        // Inizializza lo scanner
        html5QrCode = new Html5Qrcode("reader");
        
        html5QrCode.start(
            { facingMode: "environment" }, 
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                // Successo: leggiamo il testo del QR
                const parts = decodedText.split(':');
                // Formato: gs:ID_PUNTO:FIRMA
                if(parts[0] === 'gs' && parts[1] === puntoCorrente) {
                    const firma = parts[2];
                    inviaDati(puntoCorrente, firma);
                    chiudiScanner();
                } else { //debug che scrive cosa ha letto lui
                    alert("Questo QR non corrisponde alla presa selezionata!");
                }
            },
            (errorMessage) => { /* Ignora errori di scansione continua */ }
        ).catch(err => {
            console.error("Errore Camera:", err);
            alert("Impossibile accedere alla fotocamera. Controlla i permessi del browser.");
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

    async function inviaDati(id, firma) {
        try {
            const response = await fetch('/api/scansione-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer {{ session("api_token") }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id_punto: id,
                    firma: firma
                })
            });

            const data = await response.json();

            if (response.ok) {
                // Vai alla pagina della sessione attiva (già configurata in web.php)
                window.location.href = '/session/' + data.session_uuid;
            } else {
                alert("Errore API: " + (data.message || "Riprova"));
            }
        } catch (error) {
            console.error("Errore nell'invio:", error);
        }
    }
</script>
@endsection