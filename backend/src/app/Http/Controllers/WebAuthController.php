<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Utenti;

class WebAuthController extends Controller
{
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
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Tentativo di accesso: Auth::attempt controlla se email e password esistono nel DB.
        if (Auth::attempt($credentials)) {
            // Se i dati sono giusti, rigeneriamo la sessione del browser per sicurezza.
            $request->session()->regenerate();
            
            // Recuperiamo i dati dell'utente che ha appena fatto l'accesso.
            /** @var \App\Models\Utenti $user */
            $user = Auth::user();

            // 3. GESTIONE TOKEN SANCTUM:
            // Prima cancelliamo eventuali vecchi token per non averne troppi nel database.
            $user->tokens()->delete(); 
            
            // Creiamo un nuovo Token (una chiave segreta) per questo utente.
            $token = $user->createToken('web-access')->plainTextToken;
            
            // 4. MEMORIZZAZIONE:
            // Salviamo questa chiave (token) nella "Sessione" del server Laravel.
            // Questo è fondamentale: la rotta /map in web.php la leggerà da qui 
            // per passarla al JavaScript della mappa.
            session(['api_token' => $token]);

            // 5. Reindirizziamo l'utente alla pagina della mappa.
            return redirect()->intended('map');
        }

        // Se i dati sono sbagliati (password errata o email non trovata), restituiamo l'errore.
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
        'nome'                 => ['required', 'string', 'max:100'],
        'cognome'              => ['required', 'string', 'max:100'],
        'email'                => ['required', 'email', 'max:255', 'unique:utenti,email'],
        'cellulare'            => ['nullable', 'string', 'max:20'],
        //'tipo_account'         => ['required', 'in:studente,docente,personale,completo'],
        'password'             => ['required', 'confirmed', 'min:8'],
    ], [
        'email.unique'         => 'Questa email è già registrata.',
        'password.confirmed'   => 'Le password non coincidono.',
        'password.min'         => 'La password deve essere di almeno 8 caratteri.',
        'tipo_account.in'      => 'Tipo account non valido.',
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