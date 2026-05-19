<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Stazioni;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    #TODO mettere se è occupata completamente o no
    public function all(Request $request): JsonResponse
    { 
        /**
         * Recupera l'elenco completo delle stazioni con i relativi punti associati.
         * La funzione restituisce una collezione di tutte le stazioni presenti nel database,
         * includendo tramite caricamento preventivo la relazione con la tabella punti.
         *
         * @param Request $request Parametri della richiesta HTTP.
         * @return JsonResponse Risposta JSON contenente lo status e la lista delle stazioni.
         */

        try {
        // Mostriamo solo le stazioni effettivamente configurate dall'admin.
        // Quelle 'in_setup' sono appena registrate (MAC noto, ma niente coordinate
        // ne' punti) e non devono comparire sulla mappa.
        $stazioni = Stazioni::with('puntiRicarica')
            ->where('stato_setup', 'attiva')
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $stazioni
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'debug' => $e->getMessage(),
            'message' => 'Errore interno del server'
        ], 500);
    }
    }


    
    public function show(string $idStazione, Request $request): JsonResponse
    {
        /**
        * Recupera i dettagli di una singola stazione tramite il suo ID.
        * La funzione utilizza il parametro passato nell'URL per identificare la risorsa.
        * Se l'identificativo non corrisponde ad alcuna stazione, viene restituito un errore 404.
        *
        * @param string $id L'identificativo univoco (UUID) della stazione.
        * @return JsonResponse Risposta JSON con i dati della stazione o messaggio di errore.
        */

        try {
            $stazione = Stazioni::with('puntiRicarica')
                ->where('stato_setup', 'attiva')
                ->findOrFail($idStazione);

            return response()->json([
                'status' => 'success',
                'data' => $stazione
            ], 200);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Risorsa non trovata: la stazione richiesta non esiste'
            ], 404);
        }
    }
}