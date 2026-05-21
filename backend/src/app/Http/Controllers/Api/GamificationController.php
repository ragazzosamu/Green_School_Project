<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gamification_badge_catalogo;
use App\Models\Gamification_profilo_utente;
use App\Models\Sessioni_ricarica;
use App\Services\SfideSettimanaliService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GamificationController — Sprint 3.6
 *
 * GET /api/gamification/profile    → XP, livello, CO₂, streak, sessioni totali, kWh totali
 * GET /api/gamification/badges     → badge sbloccati + badge ancora da sbloccare
 * GET /api/gamification/leaderboard → top 10 utenti per XP
 */
class GamificationController extends Controller
{
    // ── GET /api/gamification/profile ────────────────────────────────────────

    public function profile(Request $request): JsonResponse
    {
        $idUtente = $request->user()->id_utente;

        // Profilo gamification (crea il record se non esiste ancora)
        $profilo = Gamification_profilo_utente::firstOrCreate(
            ['id_utente' => $idUtente],
            [
                'xp_totali'           => 0,
                'co2_risparmiata_kg'  => 0,
                'streak_giorni'       => 0,
                'data_ultima_ricarica'=> null,
            ]
        );

        // Sessioni completate e kWh totali (calcolati dal DB reale)
        $stats = DB::table('sessioni_ricarica')
            ->where('id_utente', $idUtente)
            ->whereNotNull('data_fine')
            ->selectRaw('COUNT(*) as sessioni_totali, COALESCE(SUM(quantita_kwh), 0) as kwh_totali')
            ->first();

        // XP soglia livello corrente e prossimo (formula: livello = FLOOR(SQRT(xp/100)))
        // → XP per livello N = N² × 100
        $livello      = (int) $profilo->livello;
        $xpLivelloOra = $livello * $livello * 100;
        $xpProssimo   = ($livello + 1) * ($livello + 1) * 100;
        $xpNelLivello = max(0, $profilo->xp_totali - $xpLivelloOra);
        $xpNecessari  = $xpProssimo - $xpLivelloOra;
        $percentuale  = $xpNecessari > 0 ? round(($xpNelLivello / $xpNecessari) * 100) : 100;

        return response()->json([
            'xp_totali'          => (int) $profilo->xp_totali,
            'livello'            => $livello,
            'xp_livello_corrente'=> $xpLivelloOra,
            'xp_prossimo_livello'=> $xpProssimo,
            'xp_nel_livello'     => $xpNelLivello,
            'xp_necessari'       => $xpNecessari,
            'percentuale_livello'=> $percentuale,
            'co2_risparmiata_kg' => (float) $profilo->co2_risparmiata_kg,
            'streak_giorni'      => (int) $profilo->streak_giorni,
            'sessioni_totali'    => (int) ($stats->sessioni_totali ?? 0),
            'kwh_totali'         => (float) ($stats->kwh_totali ?? 0),
        ]);
    }

    // ── GET /api/gamification/badges ─────────────────────────────────────────

    public function badges(Request $request): JsonResponse
    {
        $idUtente = $request->user()->id_utente;

        // Badge sbloccati dall'utente (con data sblocco)
        $sbloccati = DB::table('gamification_badge_utente as bu')
            ->join('gamification_badge_catalogo as bc', 'bc.id_badge', '=', 'bu.id_badge')
            ->where('bu.id_utente', $idUtente)
            ->select(
                'bc.codice',
                'bc.nome',
                'bc.descrizione',
                'bc.icona_emoji',
                'bu.data_sblocco',
            )
            ->orderBy('bu.data_sblocco', 'desc')
            ->get();

        // ID badge già sbloccati
        $idSbloccati = $sbloccati->pluck('codice')->toArray();

        // Badge ancora da sbloccare
        $daSbloccare = Gamification_badge_catalogo::whereNotIn('codice', $idSbloccati)
            ->get(['codice', 'nome', 'descrizione', 'icona_emoji', 'condizione_json']);

        return response()->json([
            'sbloccati'    => $sbloccati,
            'da_sbloccare' => $daSbloccare,
        ]);
    }

    // ── GET /api/gamification/leaderboard ────────────────────────────────────
    //
    // NOTA: livello è GENERATED COLUMN STORED su MariaDB — non va usata
    // dentro selectRaw con alias tabella né in subquery annidate perché
    // alcune versioni di MariaDB lanciano un errore SQL.
    // Soluzione: selezioniamo solo le colonne reali e ricalcoliamo livello
    // in PHP con la stessa formula FLOOR(SQRT(xp / 100)).
    //
    // Il blade si aspetta array flat con:
    //   id_utente, nome, cognome, xp_totali, livello,
    //   co2_risparmiata_kg, sessioni_totali

    public function leaderboard(Request $request): JsonResponse
    {
        // Step 1: top 10 per XP — NO livello nella select (GENERATED COLUMN)
        $top10 = DB::table('gamification_profilo_utente as g')
            ->join('utenti as u', 'u.id_utente', '=', 'g.id_utente')
            ->orderByDesc('g.xp_totali')
            ->limit(10)
            ->select(
                'u.id_utente',
                'u.nome',
                'u.cognome',
                'g.xp_totali',
                'g.co2_risparmiata_kg',
            )
            ->get();

        // Step 2: sessioni per questi utenti in una sola query
        $idUtenti = $top10->pluck('id_utente')->toArray();
        $sessioni = DB::table('sessioni_ricarica')
            ->whereIn('id_utente', $idUtenti)
            ->whereNotNull('data_fine')
            ->selectRaw('id_utente, COUNT(*) as tot')
            ->groupBy('id_utente')
            ->pluck('tot', 'id_utente');

        // Step 3: livello calcolato in PHP — stessa formula della GENERATED COLUMN
        $result = $top10->map(fn($row) => [
            'id_utente'          => $row->id_utente,
            'nome'               => $row->nome,
            'cognome'            => $row->cognome,
            'xp_totali'          => (int)   $row->xp_totali,
            'livello'            => (int)   floor(sqrt($row->xp_totali / 100)),
            'co2_risparmiata_kg' => (float) round($row->co2_risparmiata_kg, 1),
            'sessioni_totali'    => (int)   ($sessioni[$row->id_utente] ?? 0),
        ]);

        // Array diretto — il blade fa: Array.isArray(json) ? json : (json.data ?? [])
        return response()->json($result);
    }

    // ── GET /api/gamification/sessioni ───────────────────────────────────────
    // Ultime 10 sessioni dell'utente con XP guadagnati (calcolati on-the-fly)

    public function sessioni(Request $request): JsonResponse
    {
        $idUtente = $request->user()->id_utente;

        $sessioni = DB::table('sessioni_ricarica')
            ->where('id_utente', $idUtente)
            ->whereNotNull('data_fine')
            ->orderByDesc('data_inizio')
            ->limit(10)
            ->select('id_sessione', 'data_inizio', 'data_fine', 'quantita_kwh', 'costo_totale')
            ->get()
            ->map(function ($s) {
                $kwh = (float) ($s->quantita_kwh ?? 0);
                $xp  = max(5, (int) round($kwh * 10));

                // Durata in ore e minuti
                $secondi   = strtotime($s->data_fine) - strtotime($s->data_inizio);
                $ore       = floor($secondi / 3600);
                $minuti    = floor(($secondi % 3600) / 60);

                return [
                    'id_sessione' => $s->id_sessione,
                    'data'        => \Carbon\Carbon::parse($s->data_inizio)->isoFormat('D MMM YYYY'),
                    'durata'      => "{$ore}h {$minuti}m",
                    'kwh'         => round($kwh, 2),
                    'costo'       => '€ ' . number_format((float) ($s->costo_totale ?? 0), 2),
                    'xp'          => "+{$xp} XP",
                ];
            });

        return response()->json(['sessioni' => $sessioni]);
    }

    // ── GET /api/gamification/sfide ──────────────────────────────────────────
    // Le 3 sfide della settimana corrente con progresso, stato e bonus XP.
    // Il progresso viene ricalcolato dalle sessioni reali ad ogni chiamata.

    public function sfide(Request $request): JsonResponse
    {
        $idUtente = $request->user()->id_utente;

        $service = new SfideSettimanaliService();
        $sfide   = $service->aggiorna($idUtente);
        $definizioni = SfideSettimanaliService::definizioni();

        $result = $sfide->map(function ($s) use ($definizioni) {
            $def       = $definizioni[$s->codice_sfida] ?? [];
            $target    = (float) $s->target;
            $progresso = (float) $s->progresso;
            $perc      = $target > 0 ? min(100, round(($progresso / $target) * 100)) : 0;

            return [
                'codice'      => $s->codice_sfida,
                'titolo'      => $def['titolo']      ?? $s->codice_sfida,
                'descrizione' => $def['descrizione'] ?? '',
                'icona'       => $def['icona']       ?? '🎯',
                'target'      => $target,
                'progresso'   => round($progresso, 2),
                'percentuale' => $perc,
                'stato'       => $s->stato,
                'bonus_xp'    => $def['bonus_xp']    ?? 0,
            ];
        })->values();

        return response()->json(['sfide' => $result]);
    }
}