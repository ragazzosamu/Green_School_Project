<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scuola_profilo;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    /**
     * GET /api/school/profile
     * Restituisce il profilo della prima scuola trovata (se ne gestisce una sola).
     */
    public function profile(): JsonResponse
    {
        $scuola = Scuola_profilo::first();

        if (!$scuola) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nessun profilo scuola trovato.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $scuola,
        ]);
    }

    /**
     * GET /api/school/consumption?anno=2026
     * Restituisce i 12 mesi di consumi per l'anno richiesto (default: anno corrente).
     * Risposta: array di 12 elementi ordinati per mese (mesi senza dati = null).
     */
    public function consumption(): JsonResponse
    {
        $anno   = request()->query('anno', now()->year);
        $scuola = Scuola_profilo::first();

        if (!$scuola) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Nessun profilo scuola trovato.',
            ], 404);
        }

        // Recupera i consumi per l'anno richiesto
        $rawData = $scuola->consumiMensili()
            ->where('anno', $anno)
            ->orderBy('mese')
            ->get()
            ->keyBy('mese'); // indicizzati per mese (1-12)

        // Costruisce array completo di 12 mesi (null se mancante)
        $mesi = [];
        for ($m = 1; $m <= 12; $m++) {
            $record = $rawData->get($m);
            $mesi[] = $record ? [
                'mese'                  => $m,
                'consumo_elettrico_kwh' => (float) $record->consumo_elettrico_kwh,
                'consumo_termico_kwh'   => (float) $record->consumo_termico_kwh,
                'produzione_fv_kwh'     => (float) $record->produzione_fv_kwh,
                'co2_emessa_kg'         => (float) $record->co2_emessa_kg,
            ] : [
                'mese'                  => $m,
                'consumo_elettrico_kwh' => null,
                'consumo_termico_kwh'   => null,
                'produzione_fv_kwh'     => null,
                'co2_emessa_kg'         => null,
            ];
        }

        // Anni disponibili (per il selettore anno nel frontend)
        $anniDisponibili = $scuola->consumiMensili()
            ->select('anno')
            ->distinct()
            ->orderBy('anno', 'desc')
            ->pluck('anno');

        return response()->json([
            'status' => 'success',
            'anno'   => (int) $anno,
            'anni_disponibili' => $anniDisponibili,
            'data'   => $mesi,
        ]);
    }
}