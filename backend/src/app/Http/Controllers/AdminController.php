<?php

namespace App\Http\Controllers;

use App\Models\Utenti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * AdminController — Pannello amministrazione GreenSchool
 *
 * Rotte gestite:
 *   GET  /admin                     → dashboard
 *   GET  /admin/utenti              → lista utenti
 *   GET  /admin/utenti/{id}         → dettaglio utente
 *   POST /admin/utenti/{id}         → modifica utente
 *   POST /admin/utenti/{id}/toggle  → attiva/disattiva utente
 *   POST /admin/utenti/{id}/reset   → reset password utente
 *   GET  /admin/sessioni            → lista sessioni (tutte)
 *   GET  /admin/stazioni            → stato stazioni
 *   POST /admin/stazioni/{id}/toggle → metti stazione in manutenzione
 */
class AdminController extends Controller
{
    // ── DASHBOARD ────────────────────────────────────────────────────────────

    public function dashboard(): \Illuminate\View\View
    {
        $stats = [
            'utenti_totali'    => DB::table('utenti')->where('ruolo', 'utente')->count(),
            'sessioni_oggi'    => DB::table('sessioni_ricarica')
                                    ->whereDate('data_inizio', today())->count(),
            'sessioni_totali'  => DB::table('sessioni_ricarica')->count(),
            'kwh_totali'       => (float) DB::table('sessioni_ricarica')
                                    ->whereNotNull('data_fine')
                                    ->sum('quantita_kwh'),
            'stazioni_online'  => DB::table('punti_ricarica')
                                    ->where('stato_hardware', 'online')->count(),
            'stazioni_offline' => DB::table('punti_ricarica')
                                    ->whereIn('stato_hardware', ['offline', 'guasto'])->count(),
            'sessioni_attive'  => DB::table('sessioni_ricarica')
                                    ->whereNull('data_fine')->count(),
            'revenue_totale'   => (float) DB::table('sessioni_ricarica')
                                    ->whereNotNull('data_fine')
                                    ->sum('costo_totale'),
        ];

        // Sessioni ultimi 7 giorni (per grafico)
        $sessioniSettimana = DB::table('sessioni_ricarica')
            ->where('data_inizio', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(data_inizio) as giorno, COUNT(*) as totale, COALESCE(SUM(quantita_kwh),0) as kwh')
            ->groupBy('giorno')
            ->orderBy('giorno')
            ->get();

        // Utenti più attivi (top 5)
        $topUtenti = DB::table('sessioni_ricarica as s')
            ->join('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->whereNotNull('s.data_fine')
            ->selectRaw('u.nome, u.cognome, u.email, COUNT(*) as sessioni, COALESCE(SUM(s.quantita_kwh),0) as kwh')
            ->groupBy('u.id_utente', 'u.nome', 'u.cognome', 'u.email')
            ->orderByDesc('sessioni')
            ->limit(5)
            ->get();

        // Ultime 10 sessioni
        $ultimeSessioni = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->leftJoin('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->orderByDesc('s.data_inizio')
            ->limit(10)
            ->select('s.*', 'u.nome', 'u.cognome', 'u.email', 'p.id_punto')
            ->get();

        return view('admin.dashboard', compact('stats', 'sessioniSettimana', 'topUtenti', 'ultimeSessioni'));
    }

    // ── UTENTI ───────────────────────────────────────────────────────────────

    public function utenti(Request $request): \Illuminate\View\View
    {
        $query = DB::table('utenti')->where('ruolo', 'utente');

        if ($request->filled('cerca')) {
            $q = '%' . $request->cerca . '%';
            $query->where(function ($w) use ($q) {
                $w->where('nome', 'like', $q)
                  ->orWhere('cognome', 'like', $q)
                  ->orWhere('email', 'like', $q);
            });
        }

        $utenti = $query->orderBy('nome')->paginate(20)->withQueryString();

        // Aggiungo conteggio sessioni per ogni utente
        $ids = $utenti->pluck('id_utente')->toArray();
        $sessioni = DB::table('sessioni_ricarica')
            ->whereIn('id_utente', $ids)
            ->whereNotNull('data_fine')
            ->selectRaw('id_utente, COUNT(*) as tot, COALESCE(SUM(quantita_kwh),0) as kwh')
            ->groupBy('id_utente')
            ->get()
            ->keyBy('id_utente');

        return view('admin.utenti', compact('utenti', 'sessioni'));
    }

    public function dettaglioUtente(string $id): \Illuminate\View\View
    {
        $utente = DB::table('utenti')->where('id_utente', $id)->firstOrFail();

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

        return view('admin.utente-dettaglio', compact('utente', 'sessioni', 'profilo', 'badge', 'stats'));
    }

    public function modificaUtente(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'nome'         => 'required|string|max:100',
            'cognome'      => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'cellulare'    => 'required|string|max:20',
            'tipo_account' => 'required|in:completo,badge_anonimo,ospite',
            'attivo'       => 'boolean',
        ]);

        DB::table('utenti')->where('id_utente', $id)->update([
            'nome'         => $request->nome,
            'cognome'      => $request->cognome,
            'email'        => $request->email,
            'cellulare'    => $request->cellulare,
            'tipo_account' => $request->tipo_account,
            'attivo'       => $request->boolean('attivo'),
        ]);

        return back()->with('success', 'Utente aggiornato con successo.');
    }

    public function toggleUtente(string $id): \Illuminate\Http\RedirectResponse
    {
        $utente = DB::table('utenti')->where('id_utente', $id)->first();
        DB::table('utenti')->where('id_utente', $id)->update([
            'attivo' => ! $utente->attivo,
        ]);
        $stato = $utente->attivo ? 'disattivato' : 'riattivato';
        return back()->with('success', "Utente {$stato} con successo.");
    }

    public function resetPassword(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['nuova_password' => 'required|min:8']);

        DB::table('utenti')->where('id_utente', $id)->update([
            'password' => Hash::make($request->nuova_password),
        ]);

        // Revoca tutti i token Sanctum dell'utente
        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\Utenti')
            ->where('tokenable_id', $id)
            ->delete();

        return back()->with('success', 'Password aggiornata e sessioni revocate.');
    }

    // ── SESSIONI ─────────────────────────────────────────────────────────────

    public function sessioni(Request $request)
    {
        $query = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->orderByDesc('s.data_inizio');

        // (Mantieni qui i filtri di ricerca per "cerca" e "stato" che ha scritto il tuo compagno)
        if ($request->filled('cerca')) {
            $cerca = $request->cerca;
            $query->where(function($q) use ($cerca) {
                $q->where('u.email', 'like', "%$cerca%")
                  ->orWhere('u.nome', 'like', "%$cerca%")
                  ->orWhere('u.cognome', 'like', "%$cerca%");
            });
        }
        if ($request->filled('stato')) {
            if ($request->stato === 'attiva') $query->whereNull('s.data_fine');
            if ($request->stato === 'conclusa') $query->whereNotNull('s.data_fine');
        }

        $sessioni = $query->paginate(10)->withQueryString();

        //AGGIUNGI QUESTA RIGA: Prende gli utenti unici per il menu a tendina dei report
        $listaUtenti = DB::table('utenti')->select('id_utente', 'nome', 'cognome', 'email')->orderBy('cognome')->get();

        return view('admin.sessioni', compact('sessioni', 'listaUtenti'));
    }

    // ── STAZIONI ─────────────────────────────────────────────────────────────

    public function stazioni(): \Illuminate\View\View
    {
        $stazioni = DB::table('stazioni as st')
            ->leftJoin('punti_ricarica as p', 'p.id_stazione', '=', 'st.id_stazione')
            ->selectRaw('
                st.id_stazione, st.nome, st.indirizzo, st.stato_hardware,
                COUNT(p.id_punto) as punti_totali,
                SUM(CASE WHEN p.libera = 1 AND p.stato_hardware = "online" THEN 1 ELSE 0 END) as punti_liberi,
                SUM(CASE WHEN p.stato_hardware = "online" THEN 1 ELSE 0 END) as punti_online
            ')
            ->groupBy('st.id_stazione', 'st.nome', 'st.indirizzo', 'st.stato_hardware')
            ->orderBy('st.nome')
            ->get();

        return view('admin.stazioni', compact('stazioni'));
    }

    public function toggleStazione(Request $request, string $id): \Illuminate\Http\RedirectResponse
    {
        $stazione = DB::table('stazioni')->where('id_stazione', $id)->first();

        if ($stazione->stato_hardware === 'manutenzione_programmata') {
            // Togliendo la manutenzione, lo stato NON torna automaticamente "online":
            // soltanto il simulatore MQTT può portare una stazione online inviando
            // heartbeat/telemetria. Impostiamo quindi "offline" come stato neutro di
            // attesa, in modo che la mappa rifletta la realtà finché il simulatore
            // non conferma la connettività della stazione.
            $nuovoStato = 'offline';
            $messaggio  = 'Manutenzione terminata. La stazione è ora offline in attesa di heartbeat dal dispositivo.';
        } else {
            // Qualsiasi altro stato (online, offline, guasto) → messa in manutenzione.
            $nuovoStato = 'manutenzione_programmata';
            $messaggio  = 'Stazione messa in manutenzione programmata.';
        }

        DB::table('stazioni')->where('id_stazione', $id)->update([
            'stato_hardware' => $nuovoStato,
        ]);

        return back()->with('success', $messaggio);
    }

    // ── REPORT EXCEL / CSV (Aggiunto per download report giornaliero) ────────

   public function scaricaReportCsv(Request $request): \Illuminate\Http\Response
    {
        $azione = $request->input('azione'); // 'utente' oppure 'data'

        // Base della query comune a entrambi i report
        $query = DB::table('sessioni_ricarica as s')
            ->leftJoin('utenti as u', 'u.id_utente', '=', 's.id_utente')
            ->leftJoin('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->select(
                's.id_sessione',
                'u.nome',
                'u.cognome',
                'u.email',
                'p.id_punto',
                's.data_inizio',
                's.data_fine',
                's.quantita_kwh',
                's.costo_totale'
            )
            ->orderByDesc('s.data_inizio');

        if ($azione === 'utente') {
            $utenteId = $request->input('utente_id');
            if (!$utenteId) {
                abort(400, 'Seleziona un utente valido.');
            }
            $query->where('s.id_utente', $utenteId);
            $utente = DB::table('utenti')->where('id_utente', $utenteId)->first();
            $fileName = 'report_utente_' . ($utente->cognome ?? 'utente') . '_' . date('Y-m-d') . '.csv';
        } else {
            // Modalità Data (se vuota prende oggi)
            $dataReport = $request->input('data_report', date('Y-m-d'));
            $query->whereDate('s.data_inizio', $dataReport);
            $fileName = 'report_generale_' . $dataReport . '.csv';
        }

        $risultati = $query->get();

        // Inizializziamo i contatori per i totali
        $totaleKwh = 0;
        $totaleCosto = 0;

        // Generazione fisica del file CSV
        $csvHeader = ['ID Sessione', 'Nome', 'Cognome', 'Email Utente', 'Punto Ricarica', 'Data Inizio', 'Data Fine', 'kWh Erogati', 'Costo (€)'];
        $handle = fopen('php://temp', 'w');
        fputcsv($handle, $csvHeader, ';');
        
        foreach ($risultati as $row) {
            // Accumuliamo i valori numerici
            $totaleKwh += (float)($row->quantita_kwh ?? 0);
            $totaleCosto += (float)($row->costo_totale ?? 0);

            fputcsv($handle, [
                $row->id_sessione,
                $row->nome,
                $row->cognome,
                $row->email,
                $row->id_punto ?? '—',
                $row->data_inizio,
                $row->data_fine ?? 'In corso',
                number_format((float)($row->quantita_kwh ?? 0), 2, ',', ''),
                number_format((float)($row->costo_totale ?? 0), 2, ',', '')
            ], ';');
        }
        
        // Righe vuote di spaziatura estetica prima del totale
        fputcsv($handle, ['', '', '', '', '', '', '', '', ''], ';');

        // Riga dei Totali (Mettiamo la scritta "TOTALE" sotto la colonna Data Fine e i valori incolonnati correttamente)
        fputcsv($handle, [
            '', // ID Sessione
            '', // Nome
            '', // Cognome
            '', // Email
            '', // Punto
            '', // Data Inizio
            'TOTALE COMPLESSIVO:', // Etichetta posizionata prima dei dati numerici
            number_format($totaleKwh, 2, ',', ''), // kWh Erogati totali
            number_format($totaleCosto, 2, ',', '') // Costo totale
        ], ';');

        rewind($handle);
        $csvData = stream_get_contents($handle);
        fclose($handle);

        return response($csvData)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}