<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Utenti;
use Carbon\Carbon;

class WebAuthController extends Controller
{
    /**
     * Numero massimo di tentativi falliti prima del blocco.
     */
    private const MAX_TENTATIVI = 5;

    /**
     * Durata del blocco in minuti.
     */
    private const BLOCCO_MINUTI = 15;

    /**
     * Funzione che mostra semplicemente la pagina con il modulo di login.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Funzione che gestisce l'invio dei dati (Email e Password) dal modulo.
     */
    public function login(Request $request)
    {
        // 1. Validazione: controlliamo che l'utente abbia scritto bene l'email e la password.
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Recuperiamo l'utente dal DB (se esiste) per controllare lo stato del lockout
        //    PRIMA ancora di tentare l'autenticazione.
        $utente = Utenti::where('email', $credentials['email'])->first();

        if ($utente) {
            // 2a. CHECK LOCKOUT: se l'account è temporaneamente bloccato, blocchiamo subito.
            if ($utente->login_bloccato_fino && Carbon::now()->lt($utente->login_bloccato_fino)) {
                $minutiRimasti = (int) ceil(Carbon::now()->diffInMinutes($utente->login_bloccato_fino, false));
                // diffInMinutes con false restituisce un valore negativo se "fino" è nel futuro;
                // usiamo abs per sicurezza.
                $minutiRimasti = abs($minutiRimasti) ?: 1;

                throw ValidationException::withMessages([
                    'email' => [
                        "Troppi tentativi falliti. Account bloccato temporaneamente. " .
                        "Riprova tra circa {$minutiRimasti} " . ($minutiRimasti === 1 ? 'minuto' : 'minuti') . "."
                    ],
                ]);
            }
        }

        // 3. Tentativo di accesso: Auth::attempt controlla se email e password esistono nel DB.
        if (Auth::attempt($credentials)) {
            // Se i dati sono giusti, rigeneriamo la sessione del browser per sicurezza.
            $request->session()->regenerate();

            // Recuperiamo i dati dell'utente che ha appena fatto l'accesso.
            /** @var \App\Models\Utenti $user */
            $user = Auth::user();

            // 3a. CHECK ACCOUNT ATTIVO: se l'account è stato disattivato da un admin,
            // facciamo subito logout e restituiamo un errore rosso. Auth::attempt() non
            // controlla questo campo da solo, quindi il check va fatto manualmente.
            if (! $user->attivo) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => ['Il tuo account è stato disattivato. Contatta un amministratore.'],
                ]);
            }

            // 4. LOGIN RIUSCITO → azzeriamo il contatore tentativi e rimuoviamo il blocco.
            $user->login_tentativi    = 0;
            $user->login_bloccato_fino = null;
            $user->save();

            // 5. GESTIONE TOKEN SANCTUM:
            // Prima cancelliamo eventuali vecchi token per non averne troppi nel database.
            $user->tokens()->delete();

            // Creiamo un nuovo Token (una chiave segreta) per questo utente.
            $token = $user->createToken('web-access')->plainTextToken;

            // 6. MEMORIZZAZIONE:
            // Salviamo questa chiave (token) nella "Sessione" del server Laravel.
            // Questo è fondamentale: la rotta /map in web.php la leggerà da qui
            // per passarla al JavaScript della mappa.
            session(['api_token' => $token]);

            // 7. Reindirizziamo l'utente alla pagina della mappa.
            return redirect()->intended('map');
        }

        // --- LOGIN FALLITO ---

        // Se l'utente esiste nel DB, incrementiamo il contatore tentativi.
        if ($utente) {
            $utente->login_tentativi += 1;

            if ($utente->login_tentativi >= self::MAX_TENTATIVI) {
                // Tentativi esauriti: blocchiamo l'account per BLOCCO_MINUTI minuti.
                $utente->login_bloccato_fino = Carbon::now()->addMinutes(self::BLOCCO_MINUTI);
                $utente->login_tentativi     = 0; // reset così il prossimo ciclo reinizia da 0
                $utente->save();

                throw ValidationException::withMessages([
                    'email' => [
                        "Hai esaurito i tentativi disponibili. " .
                        "Account bloccato per " . self::BLOCCO_MINUTI . " minuti. Riprova più tardi."
                    ],
                ]);
            }

            $utente->save();

            $rimasti = self::MAX_TENTATIVI - $utente->login_tentativi;
            throw ValidationException::withMessages([
                'email' => [
                    "Le credenziali inserite non sono corrette. " .
                    "Tentativi rimanenti: {$rimasti}."
                ],
            ]);
        }

        // Se l'email non esiste nel DB, messaggio generico (non rivelare che l'email non esiste).
        throw ValidationException::withMessages([
            'email' => ['Le credenziali inserite non sono corrette.'],
        ]);
    }

    /**
     * Funzione per uscire dal sito (Logout).
     */
    public function logout(Request $request)
    {
        /** @var \App\Models\Utenti $user */
        $user = Auth::user();

        // Se l'utente era loggato, cancelliamo il suo token dal database così la chiave scade.
        if ($user) {
            $user->tokens()->delete();
        }

        // Chiudiamo la sessione Web di Laravel.
        Auth::logout();

        // Puliamo e invalidiamo i dati della sessione nel browser.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Riportiamo l'utente alla pagina di login.
        return redirect('/login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'nome'     => ['required', 'string', 'max:100'],
            'cognome'  => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:utenti,email'],
            'cellulare'=> ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'email.unique'       => 'Questa email è già registrata.',
            'password.confirmed' => 'Le password non coincidono.',
            'password.min'       => 'La password deve essere di almeno 8 caratteri.',
        ]);

        $utente = \App\Models\Utenti::create([
            'id_utente'    => \Illuminate\Support\Str::uuid()->toString(),
            'nome'         => $request->nome,
            'cognome'      => $request->cognome,
            'email'        => $request->email,
            'cellulare'    => $request->cellulare,
            'tipo_account' => 'completo',
            'password'     => \Illuminate\Support\Facades\Hash::make($request->password),
            'attivo'       => 1,
        ]);

        Auth::login($utente);
        $request->session()->regenerate();

        $utente->tokens()->delete();
        $token = $utente->createToken('web-access')->plainTextToken;
        session(['api_token' => $token]);

        return redirect()->intended('map');
    }
}