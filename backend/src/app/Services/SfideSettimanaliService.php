<?php

namespace App\Services;

use App\Models\Gamification_sfida_settimanale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SfideSettimanaliService
 *
 * Ogni utente ha 3 sfide a settimana (lunedì → domenica), una per metrica:
 *  - kWh ricaricati, numero sessioni, CO2 risparmiata.
 * Completando una sfida si guadagna un bonus XP una sola volta.
 */
class SfideSettimanaliService
{
    /** kg CO2 risparmiata per ogni kWh (stesso fattore ISPRA di GamificationService). */
    private const CO2_KG_PER_KWH = 0.233;

    /**
     * Le 3 sfide settimanali. La chiave è il codice_sfida salvato a DB.
     */
    public static function definizioni(): array
    {
        return [
            'kwh_settimana' => [
                'titolo'      => 'Carica green',
                'descrizione' => 'Ricarica 10 kWh questa settimana',
                'icona'       => '⚡',
                'target'      => 10.0,
                'bonus_xp'    => 50,
            ],
            'sessioni_settimana' => [
                'titolo'      => 'Cliente abituale',
                'descrizione' => 'Completa 3 ricariche questa settimana',
                'icona'       => '🔌',
                'target'      => 3.0,
                'bonus_xp'    => 50,
            ],
            'co2_settimana' => [
                'titolo'      => 'Amico del pianeta',
                'descrizione' => 'Risparmia 2.5 kg di CO2 questa settimana',
                'icona'       => '🌍',
                'target'      => 2.5,
                'bonus_xp'    => 50,
            ],
        ];
    }

    private function inizioSettimana(): Carbon
    {
        return Carbon::now()->startOfWeek();
    }

    private function fineSettimana(): Carbon
    {
        return Carbon::now()->endOfWeek();
    }

    /**
     * Garantisce che l'utente abbia le 3 sfide della settimana corrente.
     */
    public function garantisciSfide(string $idUtente): Collection
    {
        $inizio = $this->inizioSettimana();
        $fine   = $this->fineSettimana();

        foreach (self::definizioni() as $codice => $def) {
            Gamification_sfida_settimanale::firstOrCreate(
                [
                    'id_utente'    => $idUtente,
                    'codice_sfida' => $codice,
                    'data_inizio'  => $inizio,
                ],
                [
                    'target'    => $def['target'],
                    'progresso' => 0,
                    'data_fine' => $fine,
                    'stato'     => 'attiva',
                ]
            );
        }

        return $this->sfideSettimanaCorrente($idUtente);
    }

    public function sfideSettimanaCorrente(string $idUtente): Collection
    {
        return Gamification_sfida_settimanale::where('id_utente', $idUtente)
            ->where('data_inizio', $this->inizioSettimana())
            ->get();
    }

    /**
     * Ricalcola il progresso delle sfide della settimana corrente dalle
     * sessioni reali e assegna il bonus XP per quelle appena completate.
     * Idempotente: il bonus si dà una sola volta (stato 'completata').
     *
     * @return Collection le sfide aggiornate della settimana corrente
     */
    public function aggiorna(string $idUtente): Collection
    {
        try {
            $inizio = $this->inizioSettimana();
            $fine   = $this->fineSettimana();

            $this->garantisciSfide($idUtente);

            // Statistiche delle sessioni chiuse nella settimana corrente
            $stats = DB::table('sessioni_ricarica')
                ->where('id_utente', $idUtente)
                ->whereNotNull('data_fine')
                ->whereBetween('data_fine', [$inizio, $fine])
                ->selectRaw('COUNT(*) as sessioni, COALESCE(SUM(quantita_kwh), 0) as kwh')
                ->first();

            $valori = [
                'kwh_settimana'      => (float) $stats->kwh,
                'sessioni_settimana' => (int)   $stats->sessioni,
                'co2_settimana'      => round((float) $stats->kwh * self::CO2_KG_PER_KWH, 3),
            ];

            $sfide = $this->sfideSettimanaCorrente($idUtente);

            foreach ($sfide as $sfida) {
                $sfida->progresso = $valori[$sfida->codice_sfida] ?? 0;

                if ($sfida->stato === 'attiva'
                    && $sfida->progresso >= (float) $sfida->target) {
                    $sfida->stato = 'completata';
                    $bonus = self::definizioni()[$sfida->codice_sfida]['bonus_xp'] ?? 0;
                    $this->assegnaBonusXp($idUtente, $bonus);
                }

                $sfida->save();
            }

            return $sfide;

        } catch (\Throwable $e) {
            Log::error('[SfideSettimanaliService] aggiorna fallito', [
                'id_utente' => $idUtente,
                'error'     => $e->getMessage(),
            ]);
            return $this->sfideSettimanaCorrente($idUtente);
        }
    }

    /**
     * Aggiunge il bonus XP al profilo gamification dell'utente.
     */
    private function assegnaBonusXp(string $idUtente, int $bonus): void
    {
        if ($bonus <= 0) {
            return;
        }

        // Crea il profilo se non esiste ancora
        DB::table('gamification_profilo_utente')->insertOrIgnore([
            'id_utente'            => $idUtente,
            'xp_totali'            => 0,
            'co2_risparmiata_kg'   => 0,
            'streak_giorni'        => 0,
            'data_ultima_ricarica' => null,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        DB::table('gamification_profilo_utente')
            ->where('id_utente', $idUtente)
            ->update([
                'xp_totali'  => DB::raw("xp_totali + {$bonus}"),
                'updated_at' => now(),
            ]);
    }
}
