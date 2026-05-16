<?php

namespace App\Services;

use App\Models\Gamification_badge_catalogo;
use App\Models\Gamification_badge_utente;
use App\Models\Gamification_profilo_utente;
use App\Models\Sessioni_ricarica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GamificationService — Sprint 3.4
 *
 * Chiamato da SessioneService::termina() dopo la chiusura di una sessione.
 * NON usa WebSocket: il frontend aggiorna il profilo tramite il tasto "Aggiorna".
 *
 * Responsabilità:
 *  1. Calcolare XP guadagnati dalla sessione (formula: kWh × 10, +bonus notturno)
 *  2. Calcolare CO₂ risparmiata (0.233 kg CO₂ per kWh — fattore ISPRA)
 *  3. Aggiornare streak giorni consecutivi
 *  4. Persistere tutto su gamification_profilo_utente
 *  5. Valutare le regole badge del catalogo e sbloccare quelli appena maturati
 */
class GamificationService
{
    // ── Costanti di gioco ─────────────────────────────────────────────────────

    /** XP per ogni kWh erogato */
    private const XP_PER_KWH = 10;

    /** Bonus XP per ricarica notturna (22:00–06:00) */
    private const XP_BONUS_NOTTURNO = 20;

    /**
     * kg CO₂ risparmiata per ogni kWh ricaricato rispetto a un veicolo termico.
     * Fonte: ISPRA — emissioni medie rete elettrica italiana (2023).
     */
    private const CO2_KG_PER_KWH = 0.233;

    // ── Entry point pubblico ──────────────────────────────────────────────────

    /**
     * Aggiorna il profilo gamification dell'utente dopo la chiusura di una sessione.
     *
     * @param  string $idSessione  UUID della sessione appena terminata
     * @param  string $idUtente    UUID dell'utente
     * @param  float  $kwhTotali   kWh erogati nella sessione (da sp_termina_sessione)
     * @return array{xp_guadagnati:int, co2_aggiunta:float, badge_sbloccati:array, livello_attuale:int}
     */
    public function aggiorna(string $idSessione, string $idUtente, float $kwhTotali): array
    {
        // Recupero la sessione per sapere l'orario di inizio (badge notturno, streak)
        $sessione = Sessioni_ricarica::find($idSessione);

        if (! $sessione) {
            Log::warning('[GamificationService] Sessione non trovata', ['id' => $idSessione]);
            return $this->emptyResult();
        }

        // 1. Calcola XP e CO₂
        $isNotturna   = $this->isRicaricaNotturna($sessione->data_inizio);
        $xpGuadagnati = $this->calcolaXp($kwhTotali, $isNotturna);
        $co2Aggiunta  = round($kwhTotali * self::CO2_KG_PER_KWH, 3);

        // 2. Aggiorna (o crea) il profilo in modo atomico con una singola query
        $profilo = $this->aggiornaProfilo($idUtente, $xpGuadagnati, $co2Aggiunta, $sessione->data_inizio);

        if (! $profilo) {
            return $this->emptyResult();
        }

        // 3. Controlla e sblocca eventuali badge
        $badgeSbloccati = $this->aggiornaPunteggioBadge($idUtente, $idSessione, $profilo);

        Log::info('[GamificationService] Profilo aggiornato', [
            'id_utente'      => $idUtente,
            'xp_guadagnati'  => $xpGuadagnati,
            'co2_aggiunta'   => $co2Aggiunta,
            'livello'        => $profilo->livello,
            'badge_sbloccati'=> count($badgeSbloccati),
        ]);

        return [
            'xp_guadagnati'   => $xpGuadagnati,
            'co2_aggiunta'    => $co2Aggiunta,
            'badge_sbloccati' => $badgeSbloccati,
            'livello_attuale' => (int) $profilo->livello,
        ];
    }

    // ── Logica XP ─────────────────────────────────────────────────────────────

    private function calcolaXp(float $kwhTotali, bool $isNotturna): int
    {
        $xp = (int) round($kwhTotali * self::XP_PER_KWH);

        if ($isNotturna) {
            $xp += self::XP_BONUS_NOTTURNO;
        }

        // XP minimi garantiti per ogni sessione (anche se kWh = 0)
        return max($xp, 5);
    }

    /**
     * Una ricarica è "notturna" se inizia tra le 22:00 e le 06:00.
     */
    private function isRicaricaNotturna(mixed $dataInizio): bool
    {
        if (! $dataInizio) {
            return false;
        }

        $ora = is_string($dataInizio)
            ? (int) date('H', strtotime($dataInizio))
            : (int) $dataInizio->format('H');

        return $ora >= 22 || $ora < 6;
    }

    // ── Aggiornamento profilo ─────────────────────────────────────────────────

    /**
     * Esegue un upsert atomico su gamification_profilo_utente.
     * Usa insertOrIgnore + update separati per compatibilità MariaDB con
     * le GENERATED COLUMN (ON DUPLICATE KEY UPDATE non funziona bene su di esse).
     */
    private function aggiornaProfilo(
        string $idUtente,
        int    $xpGuadagnati,
        float  $co2Aggiunta,
        mixed  $dataUltimaRicarica
    ): ?Gamification_profilo_utente {
        try {
            DB::transaction(function () use ($idUtente, $xpGuadagnati, $co2Aggiunta, $dataUltimaRicarica) {

                // Crea il profilo se non esiste ancora
                DB::table('gamification_profilo_utente')->insertOrIgnore([
                    'id_utente'           => $idUtente,
                    'xp_totali'           => 0,
                    'co2_risparmiata_kg'  => 0,
                    'streak_giorni'       => 0,
                    'data_ultima_ricarica'=> null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // Incrementa xp e co2, aggiorna streak e data ultima ricarica
                $nuovoStreak = $this->calcolaStreak($idUtente, $dataUltimaRicarica);

                DB::table('gamification_profilo_utente')
                    ->where('id_utente', $idUtente)
                    ->update([
                        'xp_totali'           => DB::raw("xp_totali + {$xpGuadagnati}"),
                        'co2_risparmiata_kg'  => DB::raw("co2_risparmiata_kg + {$co2Aggiunta}"),
                        'streak_giorni'       => $nuovoStreak,
                        'data_ultima_ricarica'=> $dataUltimaRicarica,
                        'updated_at'          => now(),
                    ]);
            });

            // Ricarica fresca per avere livello (GENERATED COLUMN aggiornata da MariaDB)
            return Gamification_profilo_utente::find($idUtente);

        } catch (\Throwable $e) {
            Log::error('[GamificationService] aggiornaProfilo fallito', [
                'id_utente' => $idUtente,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calcola il nuovo streak.
     *  - Se l'ultima ricarica era ieri → streak + 1
     *  - Se l'ultima ricarica era oggi  → streak invariato (stessa giornata)
     *  - Altrimenti                      → streak = 1 (si riparte)
     */
    private function calcolaStreak(string $idUtente, mixed $dataOggi): int
    {
        $profilo = DB::table('gamification_profilo_utente')
            ->where('id_utente', $idUtente)
            ->select('streak_giorni', 'data_ultima_ricarica')
            ->first();

        if (! $profilo || ! $profilo->data_ultima_ricarica) {
            return 1;
        }

        $ultimaData = \Carbon\Carbon::parse($profilo->data_ultima_ricarica)->startOfDay();
        $oggi       = \Carbon\Carbon::parse($dataOggi)->startOfDay();
        $diff       = $ultimaData->diffInDays($oggi);

        if ($diff === 0) {
            // Stessa giornata: streak invariato
            return (int) $profilo->streak_giorni;
        }

        if ($diff === 1) {
            // Giorno consecutivo
            return (int) $profilo->streak_giorni + 1;
        }

        // Gap di più giorni: streak si azzera
        return 1;
    }

    // ── Badge checker ─────────────────────────────────────────────────────────

    /**
     * Controlla TUTTI i badge del catalogo e sblocca quelli appena maturati.
     * È idempotente: il primary key composito (id_utente, id_badge) impedisce duplicati.
     *
     * @return array<int, array{codice:string, nome:string, icona_emoji:string}>
     */
    private function aggiornaPunteggioBadge(
        string                        $idUtente,
        string                        $idSessione,
        Gamification_profilo_utente   $profilo
    ): array {
        // Badge già sbloccati dall'utente (evita query per badge)
        $giaSbloccati = DB::table('gamification_badge_utente')
            ->where('id_utente', $idUtente)
            ->pluck('id_badge')
            ->toArray();

        $catalogo = Gamification_badge_catalogo::all();
        $nuoviBadge = [];

        foreach ($catalogo as $badge) {
            if (in_array($badge->id_badge, $giaSbloccati, true)) {
                continue; // già sbloccato, skip
            }

            if ($this->verificaCondizione($badge->condizione_json, $idUtente, $profilo)) {
                $this->sbloccaBadge($idUtente, $badge->id_badge, $idSessione);
                $nuoviBadge[] = [
                    'codice'      => $badge->codice,
                    'nome'        => $badge->nome,
                    'icona_emoji' => $badge->icona_emoji,
                ];
            }
        }

        return $nuoviBadge;
    }

    /**
     * Valuta la regola JSON del badge rispetto allo stato corrente dell'utente.
     *
     * Tipi supportati (matching con il seeder):
     *  - conteggio_sessioni         → numero totale di sessioni completate
     *  - soglia_co2                 → co2_risparmiata_kg cumulativa
     *  - conteggio_sessioni_fascia  → sessioni in fascia oraria notturna (22-06)
     */
    private function verificaCondizione(
        array                       $condizione,
        string                      $idUtente,
        Gamification_profilo_utente $profilo
    ): bool {
        $tipo      = $condizione['tipo']      ?? '';
        $operatore = $condizione['operatore'] ?? '>=';
        $valore    = $condizione['valore']    ?? 0;

        $valoreReale = match ($tipo) {

            'conteggio_sessioni' => DB::table('sessioni_ricarica')
                ->where('id_utente', $idUtente)
                ->whereNotNull('data_fine')
                ->count(),

            'soglia_co2' => (float) $profilo->co2_risparmiata_kg,

            'conteggio_sessioni_fascia' => $this->contaSessioniNotturne($idUtente),

            default => null,
        };

        if ($valoreReale === null) {
            Log::warning('[GamificationService] tipo condizione badge sconosciuto', ['tipo' => $tipo]);
            return false;
        }

        return match ($operatore) {
            '>='    => $valoreReale >= $valore,
            '>'     => $valoreReale >  $valore,
            '='     => $valoreReale == $valore,
            '<='    => $valoreReale <= $valore,
            '<'     => $valoreReale <  $valore,
            default => false,
        };
    }

    /**
     * Conta le sessioni dell'utente che iniziano in fascia 22:00–06:00.
     */
    private function contaSessioniNotturne(string $idUtente): int
    {
        return DB::table('sessioni_ricarica')
            ->where('id_utente', $idUtente)
            ->whereNotNull('data_fine')
            ->where(function ($q) {
                $q->whereRaw('HOUR(data_inizio) >= 22')
                  ->orWhereRaw('HOUR(data_inizio) < 6');
            })
            ->count();
    }

    /**
     * Inserisce il record di sblocco badge (ignora se esiste già).
     */
    private function sbloccaBadge(string $idUtente, int $idBadge, string $idSessione): void
    {
        try {
            DB::table('gamification_badge_utente')->insertOrIgnore([
                'id_utente'           => $idUtente,
                'id_badge'            => $idBadge,
                'data_sblocco'        => now(),
                'id_sessione_trigger' => $idSessione,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GamificationService] sbloccaBadge fallito', [
                'id_utente' => $idUtente,
                'id_badge'  => $idBadge,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    private function emptyResult(): array
    {
        return [
            'xp_guadagnati'   => 0,
            'co2_aggiunta'    => 0.0,
            'badge_sbloccati' => [],
            'livello_attuale' => 0,
        ];
    }
}