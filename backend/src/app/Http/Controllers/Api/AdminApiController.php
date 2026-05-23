<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * AdminApiController — Endpoint JSON per il pannello admin React
 *
 * Tutte le rotte sono protette da:
 *   - auth:sanctum  (token Bearer)
 *   - middleware 'admin.api' (ruolo === 'admin', restituisce 403 JSON)
 *
 * GET  /api/admin/dashboard
 * GET  /api/admin/utenti
 * GET  /api/admin/utenti/{id}
 * PUT  /api/admin/utenti/{id}
 * POST /api/admin/utenti/{id}/toggle
 * POST /api/admin/utenti/{id}/reset
 * GET  /api/admin/sessioni
 * GET  /api/admin/stazioni
 * POST /api/admin/stazioni/{id}/toggle
 * GET  /api/admin/stazioni/{id}/setup
 * POST /api/admin/stazioni/{id}/setup
 * GET  /api/admin/report/csv
 */
class AdminApiController extends Controller
{
    // ── DASHBOARD ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = [
            'utenti_totali'   => DB::table('utenti')->where('ruolo', 'utente')->count(),
            'sessioni_oggi'   => DB::table('sessioni_ricarica')->whereDate('data_inizio', today())->count(),
            'sessioni_totali' => DB::table('sessioni_ricarica')->count(),
            'kwh_totali'      => (float) DB::table('sessioni_ricarica')->whereNotNull('data_fine')->sum('quantita_kwh'),
            'stazioni_online' => DB::table('stazioni as st')
                ->where('st.stato_setup', 'attiva')
                ->where('st.in_manutenzione', false)
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('punti_ricarica as p')
                    ->whereColumn('p.id_stazione', 'st.id_stazione')
                    ->where('p.stato_hardware', 'online'))
                ->count(),
            'stazioni_offline' => DB::table('stazioni as st')
                ->where('st.stato_setup', 'attiva')
                ->where(fn($q) => $q->where('st.in_manutenzione', true)
                    ->orWhereNotExists(fn($q2) => $q2->select(DB::raw(1))
                        ->from('punti_ricarica as p')
                        ->whereColumn('p.id_stazione', 'st.id_stazione')
                        ->where('p.stato_hardware', 'online')))
                ->count(),
            'sessioni_attive' => DB::table('sessioni_ricarica')->whereNull('data_fine')->count(),
            'revenue_totale'  => (float) DB::table('sessioni_ricarica')->whereNotNull('data_fine')->sum('costo_totale'),
        ];

        $sessioniSettimana = DB::table('sessioni_ricarica')
            ->where('data_inizio', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(data_inizio) as giorno, COUNT(*) as totale, COALESCE(SUM(quantita_kwh),0) as kwh')
            ->groupBy('giorno')
            ->orderBy('giorno')
            ->get();

        $topUtenti = DB::table('sessioni_ricarica as s')
            ->join('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->whereNotNull('s.data_fine')
            ->selectRaw('u.nome, u.cognome, u.email, COUNT(*) as sessioni, COALESCE(SUM(s.quantita_kwh),0) as kwh')
            ->groupBy('u.id_utente', 'u.nome', 'u.cognome', 'u.email')
            ->orderByDesc('sessioni')
            ->limit(5)
            ->get();

        $ultimeSessioni = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->leftJoin('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->orderByDesc('s.data_inizio')
            ->limit(10)
            ->select('s.*', 'u.nome', 'u.cognome', 'u.email', 'p.id_punto as punto_id')
            ->get();

        return response()->json([
            'stats'              => $stats,
            'sessioni_settimana' => $sessioniSettimana,
            'top_utenti'         => $topUtenti,
            'ultime_sessioni'    => $ultimeSessioni,
        ]);
    }

    // ── UTENTI ────────────────────────────────────────────────────────────────

    public function utenti(Request $request)
    {
        $query = DB::table('utenti')->where('ruolo', 'utente');

        if ($request->filled('cerca')) {
            $q = '%' . $request->cerca . '%';
            $query->where(fn($w) => $w->where('nome', 'like', $q)
                ->orWhere('cognome', 'like', $q)
                ->orWhere('email', 'like', $q));
        }

        $utenti = $query->orderBy('nome')->paginate(20)->withQueryString();

        $ids     = $utenti->pluck('id_utente')->toArray();
        $sessioni = DB::table('sessioni_ricarica')
            ->whereIn('id_utente', $ids)
            ->whereNotNull('data_fine')
            ->selectRaw('id_utente, COUNT(*) as tot, COALESCE(SUM(quantita_kwh),0) as kwh')
            ->groupBy('id_utente')
            ->get()
            ->keyBy('id_utente');

        // Arricchisci ogni utente con i dati sessione
        $utenti->getCollection()->transform(function ($u) use ($sessioni) {
            $u->sessioni_tot = $sessioni[$u->id_utente]->tot ?? 0;
            $u->kwh_tot      = (float) ($sessioni[$u->id_utente]->kwh ?? 0);
            return $u;
        });

        return response()->json($utenti);
    }

    public function dettaglioUtente(string $id)
    {
        $utente = DB::table('utenti')->where('id_utente', $id)->first();
        if (! $utente) return response()->json(['message' => 'Utente non trovato.'], 404);

        $sessioni = DB::table('sessioni_ricarica as s')
            ->leftJoin('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->where('s.id_utente', $id)
            ->orderByDesc('s.data_inizio')
            ->select('s.*', 'p.id_punto as punto')
            ->paginate(10);

        $profilo = DB::table('gamification_profilo_utente')->where('id_utente', $id)->first();

        $badge = DB::table('gamification_badge_utente as bu')
            ->join('gamification_badge_catalogo as bc', 'bc.id_badge', '=', 'bu.id_badge')
            ->where('bu.id_utente', $id)
            ->select('bc.nome', 'bc.icona_emoji', 'bu.data_sblocco')
            ->get();

        $stats = [
            'sessioni_totali' => DB::table('sessioni_ricarica')->where('id_utente', $id)->whereNotNull('data_fine')->count(),
            'kwh_totali'      => (float) DB::table('sessioni_ricarica')->where('id_utente', $id)->whereNotNull('data_fine')->sum('quantita_kwh'),
            'spesa_totale'    => (float) DB::table('sessioni_ricarica')->where('id_utente', $id)->whereNotNull('data_fine')->sum('costo_totale'),
        ];

        return response()->json([
            'utente'  => $utente,
            'sessioni' => $sessioni,
            'profilo'  => $profilo,
            'badge'    => $badge,
            'stats'    => $stats,
        ]);
    }

    public function modificaUtente(Request $request, string $id)
    {
        $data = $request->validate([
            'nome'         => 'required|string|max:100',
            'cognome'      => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'cellulare'    => 'required|string|max:20',
            'tipo_account' => 'required|in:completo,badge_anonimo,ospite',
            'attivo'       => 'boolean',
        ]);

        $updated = DB::table('utenti')->where('id_utente', $id)->update($data);
        if (! $updated) return response()->json(['message' => 'Utente non trovato.'], 404);

        return response()->json(['message' => 'Utente aggiornato con successo.']);
    }

    public function toggleUtente(string $id)
    {
        $utente = DB::table('utenti')->where('id_utente', $id)->first();
        if (! $utente) return response()->json(['message' => 'Utente non trovato.'], 404);

        DB::table('utenti')->where('id_utente', $id)->update(['attivo' => ! $utente->attivo]);
        $stato = $utente->attivo ? 'disattivato' : 'riattivato';

        return response()->json(['message' => "Utente {$stato} con successo.", 'attivo' => ! $utente->attivo]);
    }

    public function resetPassword(Request $request, string $id)
    {
        $request->validate(['nuova_password' => 'required|min:8']);

        DB::table('utenti')->where('id_utente', $id)->update([
            'password' => Hash::make($request->nuova_password),
        ]);

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\Utenti')
            ->where('tokenable_id', $id)
            ->delete();

        return response()->json(['message' => 'Password aggiornata e sessioni revocate.']);
    }

    // ── SESSIONI ──────────────────────────────────────────────────────────────

    public function sessioni(Request $request)
    {
        $query = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->orderByDesc('s.data_inizio');

        if ($request->filled('cerca')) {
            $cerca = $request->cerca;
            $query->where(fn($q) => $q->where('u.email', 'like', "%$cerca%")
                ->orWhere('u.nome', 'like', "%$cerca%")
                ->orWhere('u.cognome', 'like', "%$cerca%"));
        }

        if ($request->filled('stato')) {
            if ($request->stato === 'attiva')   $query->whereNull('s.data_fine');
            if ($request->stato === 'conclusa') $query->whereNotNull('s.data_fine');
        }

        $sessioni    = $query->select('s.*', 'u.nome', 'u.cognome', 'u.email')->paginate(20)->withQueryString();
        $listaUtenti = DB::table('utenti')->select('id_utente', 'nome', 'cognome', 'email')->orderBy('cognome')->get();

        // Sessioni aperte -> quantita_kwh in DB e' 0 fino a sp_termina_sessione.
        // Iniettiamo il valore "live" da Redis cosi' il pannello admin React
        // mostra i kWh accumulati anche per le sessioni ancora in corso.
        $sessioniService = app(\App\Services\SessioneService::class);
        foreach ($sessioni->items() as $s) {
            if ($s->data_fine === null) {
                $s->quantita_kwh = $sessioniService->kwhCorrenti($s->id_sessione);
            }
        }

        return response()->json(['sessioni' => $sessioni, 'lista_utenti' => $listaUtenti]);
    }

    // ── STAZIONI ──────────────────────────────────────────────────────────────

    public function stazioni()
    {
        $stazioni = DB::table('stazioni as st')
            ->leftJoin('punti_ricarica as p', 'p.id_stazione', '=', 'st.id_stazione')
            ->selectRaw('
                st.id_stazione, st.nome, st.indirizzo, st.stato_setup, st.in_manutenzione,
                COUNT(p.id_punto) as punti_totali,
                SUM(CASE WHEN p.libera = 1 AND p.stato_hardware = "online" THEN 1 ELSE 0 END) as punti_liberi,
                SUM(CASE WHEN p.stato_hardware = "online" THEN 1 ELSE 0 END) as punti_online
            ')
            ->groupBy('st.id_stazione', 'st.nome', 'st.indirizzo', 'st.stato_setup', 'st.in_manutenzione')
            ->orderBy('st.stato_setup')
            ->orderBy('st.nome')
            ->get()
            ->map(function ($s) {
                $s->online = ! $s->in_manutenzione && (int) $s->punti_online > 0;
                return $s;
            });

        return response()->json($stazioni);
    }

    public function setupStazione(string $id)
    {
        $stazione = DB::table('stazioni')->where('id_stazione', $id)->first();
        if (! $stazione) return response()->json(['message' => 'Stazione non trovata.'], 404);

        $punti = DB::table('punti_ricarica')->where('id_stazione', $id)->orderBy('id_punto')->get();

        return response()->json(['stazione' => $stazione, 'punti' => $punti]);
    }

    public function completaSetupStazione(Request $request, string $id, MqttService $mqtt)
    {
        $request->validate([
            'nome'        => 'required|string|max:100',
            'indirizzo'   => 'nullable|string|max:255',
            'latitudine'  => 'required|numeric|between:-90,90',
            'longitudine' => 'required|numeric|between:-180,180',
            'tipo_area'   => 'required|in:pubblico,privato,aziendale',
            'punti'       => 'required|array|min:1',
            'punti.*.tipo_veicolo'    => 'required|in:auto,bici,monopattino',
            'punti.*.tipo_connettore' => 'nullable|string|max:30',
            'punti.*.potenza_max_kw'  => 'nullable|numeric|min:0',
        ]);

        $puntiEsistenti = DB::table('punti_ricarica')->where('id_stazione', $id)
            ->pluck('id_punto')->map(fn($v) => (string) $v)->sort()->values()->all();

        $puntiPosted = collect(array_keys($request->input('punti', [])))
            ->map(fn($v) => (string) $v)->sort()->values()->all();

        if ($puntiEsistenti !== $puntiPosted) {
            return response()->json(['message' => 'I punti inviati non corrispondono a quelli registrati dalla colonnina.'], 422);
        }

        DB::transaction(function () use ($request, $id) {
            DB::table('stazioni')->where('id_stazione', $id)->update([
                'nome'             => $request->nome,
                'indirizzo'        => $request->indirizzo,
                'latitudine'       => $request->latitudine,
                'longitudine'      => $request->longitudine,
                'coordinata'       => DB::raw("ST_GeomFromText('POINT({$request->latitudine} {$request->longitudine})')"),
                'tipo_area'        => $request->tipo_area,
                'stato_setup'      => 'attiva',
                'in_manutenzione'  => false,
                'data_attivazione' => now()->toDateString(),
            ]);

            foreach ($request->input('punti', []) as $idPunto => $p) {
                DB::table('punti_ricarica')
                    ->where('id_stazione', $id)
                    ->where('id_punto', (string) $idPunto)
                    ->update([
                        'tipo_veicolo'    => $p['tipo_veicolo'],
                        'tipo_connettore' => $p['tipo_connettore'] ?? null,
                        'potenza_max_kw'  => $p['potenza_max_kw'] ?? null,
                    ]);
            }
        });

        try {
            $puntiCreati = DB::table('punti_ricarica')->where('id_stazione', $id)->orderBy('id_punto')->pluck('id_punto')->all();
            $mqtt->publish("stazione/{$id}/ready", json_encode(['comando' => 'READY', 'id_punti' => $puntiCreati]));
        } catch (\Throwable $e) {
            Log::warning('[Admin API] publish ready fallito', ['err' => $e->getMessage(), 'id' => $id]);
        }

        return response()->json(['message' => 'Stazione configurata e attivata.']);
    }

    public function toggleStazione(Request $request, string $id, MqttService $mqtt)
    {
        $stazione = DB::table('stazioni')->where('id_stazione', $id)->first();
        if (! $stazione) return response()->json(['message' => 'Stazione non trovata.'], 404);

        $nuovoStato = ! (bool) $stazione->in_manutenzione;

        if ($nuovoStato) {
            $sessioneAttiva = DB::table('sessioni_ricarica')
                ->where('id_stazione', $id)->whereNull('data_fine')->exists();

            if ($sessioneAttiva) {
                return response()->json(['message' => 'Impossibile mettere in manutenzione: ricarica in corso sulla stazione.'], 409);
            }
        }

        DB::table('stazioni')->where('id_stazione', $id)->update(['in_manutenzione' => $nuovoStato]);

        if ($nuovoStato) {
            DB::table('punti_ricarica')->where('id_stazione', $id)->update(['stato_hardware' => 'offline']);
            \App\Events\StazioneStatusChanged::dispatch($id, false);
        }

        try {
            $mqtt->publish("stazione/{$id}/manutenzione", json_encode(['comando' => 'manutenzione', 'on' => $nuovoStato]));
        } catch (\Throwable $e) {
            Log::warning('[Admin API] publish manutenzione fallito', ['err' => $e->getMessage(), 'id' => $id]);
        }

        $msg = $nuovoStato
            ? 'Stazione messa in manutenzione e fermata.'
            : "Manutenzione terminata. La stazione tornerà online al prossimo heartbeat.";

        return response()->json(['message' => $msg, 'in_manutenzione' => $nuovoStato]);
    }

    // ── REPORT CSV ────────────────────────────────────────────────────────────

    public function scaricaReportCsv(Request $request)
    {
        $azione = $request->input('azione');

        $query = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->leftJoin('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->select('s.id_sessione', 'u.nome', 'u.cognome', 'u.email', 'p.id_punto',
                's.data_inizio', 's.data_fine', 's.quantita_kwh', 's.costo_totale')
            ->orderByDesc('s.data_inizio');

        if ($azione === 'utente') {
            $utenteId = $request->input('utente_id');
            if (! $utenteId) return response()->json(['message' => 'Seleziona un utente valido.'], 400);
            $query->where('s.id_utente', $utenteId);
            $utente   = DB::table('utenti')->where('id_utente', $utenteId)->first();
            $fileName = 'report_utente_' . ($utente->cognome ?? 'utente') . '_' . date('Y-m-d') . '.csv';
        } else {
            $dataReport = $request->input('data_report', date('Y-m-d'));
            $query->whereDate('s.data_inizio', $dataReport);
            $fileName = 'report_generale_' . $dataReport . '.csv';
        }

        $risultati  = $query->get();
        $totaleKwh  = 0;
        $totaleCosto = 0;

        $handle = fopen('php://temp', 'w');
        fputcsv($handle, ['ID Sessione', 'Nome', 'Cognome', 'Email Utente', 'Punto Ricarica', 'Data Inizio', 'Data Fine', 'kWh Erogati', 'Costo (€)'], ';');

        foreach ($risultati as $row) {
            $totaleKwh  += (float)($row->quantita_kwh ?? 0);
            $totaleCosto += (float)($row->costo_totale ?? 0);
            fputcsv($handle, [
                $row->id_sessione, $row->nome, $row->cognome, $row->email,
                $row->id_punto ?? '—', $row->data_inizio, $row->data_fine ?? 'In corso',
                number_format((float)($row->quantita_kwh ?? 0), 2, ',', ''),
                number_format((float)($row->costo_totale ?? 0), 2, ',', ''),
            ], ';');
        }

        fputcsv($handle, ['', '', '', '', '', '', 'TOTALE COMPLESSIVO:',
            number_format($totaleKwh, 2, ',', ''), number_format($totaleCosto, 2, ',', '')], ';');

        rewind($handle);
        $csvData = stream_get_contents($handle);
        fclose($handle);

        return response($csvData)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
