<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Codice monouso a 6 cifre: UNO per stazione (visualizzato sul display
 * della colonnina), valido per qualsiasi punto della stessa stazione.
 *
 * Modello dati su Redis:
 *   codice:{id_stazione}  ->  "NNNNNN"   (TTL TTL_CODICE)
 *
 * La stazione pubblica un nuovo codice ogni CODICE_INTERVAL secondi.
 * TTL 60s in Redis: l'utente ha sempre tempo a sufficienza per leggere il
 * codice dal display e digitarlo nell'app anche se la generazione successiva
 * arriva nel mezzo.
 *
 * Verifica: l'utente digita il codice nell'app, il backend itera le
 * stazioni 'attiva' e cerca quale ha quel codice. Restituisce solo l'id
 * della stazione; il punto specifico viene scelto al cavo_collegato.
 * Il codice resta in Redis fino alla scadenza naturale (non si fa forget):
 * l'unicita' del rendez-vous e' garantita a valle dal SETNX su
 * codice_pending:{id_stazione} (SessioneService::memorizzaCodiceInAttesa).
 */
class CodiceMonousoService
{
    public const TTL_CODICE = 60;

    public function memorizza(string $idStazione, string $codice): void
    {
        $codice = $this->normalizza($codice);
        if ($codice === null) return;

        Cache::put($this->key($idStazione), $codice, self::TTL_CODICE);
    }

    /**
     * Verifica un codice: ritorna id_stazione o null se invalido/scaduto.
     */
    public function verifica(string $codice): ?string
    {
        $codice = $this->normalizza($codice);
        if ($codice === null) return null;

        $stazioni = DB::table('stazioni')
            ->where('stato_setup', 'attiva')
            ->pluck('id_stazione');

        foreach ($stazioni as $idStazione) {
            if (Cache::get($this->key($idStazione)) === $codice) {
                return $idStazione;
            }
        }

        return null;
    }

    private function normalizza(string $codice): ?string
    {
        $codice = trim($codice);
        return preg_match('/^\d{6}$/', $codice) ? $codice : null;
    }

    private function key(string $idStazione): string
    {
        return "codice:{$idStazione}";
    }
}
