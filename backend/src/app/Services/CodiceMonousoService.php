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
 * Verifica: l'id_stazione arriva dalla URL (POST /api/{id_stazione}/verifica-codice),
 * quindi il backend confronta direttamente il codice digitato con quello
 * salvato in Redis per QUELLA stazione. Niente iterazione su tutte le
 * stazioni attive. Il punto specifico verra' scelto al cavo_collegato.
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
     * Verifica un codice contro UNA specifica stazione.
     * Ritorna true se il codice digitato coincide con quello in Redis
     * per quella stazione (e la stazione e' attiva), false altrimenti.
     */
    public function verifica(string $idStazione, string $codice): bool
    {
        $codice = $this->normalizza($codice);
        if ($codice === null) return false;

        $esiste = DB::table('stazioni')
            ->where('id_stazione', $idStazione)
            ->where('stato_setup', 'attiva')
            ->exists();
        if (! $esiste) return false;

        return Cache::get($this->key($idStazione)) === $codice;
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
