<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gamification_badge_catalogo;
use App\Models\Gamification_profilo_utente;
use App\Models\Sessioni_ricarica;
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

    public function leaderboard(Request $request): JsonResponse
    {
        $idUtente = $request->user()->id_utente;

        // Top 10 per XP
        $top10 = DB::table('gamification_profilo_utente as g')
            ->join('utenti as u', 'u.id_utente', '=', 'g.id_utente')
            ->orderByDesc('g.xp_totali')
            ->limit(10)
            ->selectRaw('
                u.nome,
                u.cognome,
                g.xp_totali,
                g.livello,
                g.co2_risparmiata_kg,
                g.id_utente
            ')
            ->get()
            ->map(function ($row, $index) use ($idUtente) {
                return [
                    'posizione'          => $index + 1,
                    'nome'               => $row->nome . ' ' . mb_substr($row->cognome, 0, 1) . '.',
                    'xp_totali'          => (int) $row->xp_totali,
                    'livello'            => (int) $row->livello,
                    'co2_risparmiata_kg' => (float) $row->co2_risparmiata_kg,
                    'sei_tu'             => $row->id_utente === $idUtente,
                ];
            });

        // Posizione dell'utente corrente (anche se non è in top 10)
        $posizione = DB::table('gamification_profilo_utente')
            ->where('xp_totali', '>', function ($q) use ($idUtente) {
                $q->from('gamification_profilo_utente')
                  ->where('id_utente', $idUtente)
                  ->select('xp_totali');
            })
            ->count() + 1;

        return response()->json([
            'classifica'        => $top10,
            'posizione_utente'  => $posizione,
        ]);
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
                $kwh       = (float) ($s->quantita_kwh ?? 0);
                $ora       = (int) date('H', strtotime($s->data_inizio));
                $notturna  = $ora >= 22 || $ora < 6;
                $xp        = max(5, (int) round($kwh * 10) + ($notturna ? 20 : 0));

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
}