<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utenti;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Stesse regole di WebAuthController: 5 tentativi falliti, poi blocco
     * dell'account per 15 minuti. Senza queste regole un attaccante puo'
     * bypassare il lockout web colpendo direttamente /api/login.
     */
    private const MAX_TENTATIVI = 5;
    private const BLOCCO_MINUTI = 15;

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = Utenti::where('email', $request->email)->first();

        // 1. Lockout attivo? Blocchiamo prima di toccare la password.
        if ($user && $user->login_bloccato_fino && Carbon::now()->lt($user->login_bloccato_fino)) {
            $minutiRimasti = max(1, abs((int) ceil(Carbon::now()->diffInMinutes($user->login_bloccato_fino, false))));
            return response()->json([
                'message' => "Troppi tentativi falliti. Account bloccato. Riprova tra circa {$minutiRimasti} "
                           . ($minutiRimasti === 1 ? 'minuto' : 'minuti') . '.',
            ], 423);
        }

        // 2. Credenziali errate?
        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Incrementiamo il contatore solo se l'utente esiste (altrimenti
            // un attaccante puo' enumerare quali email sono registrate).
            if ($user) {
                $user->login_tentativi += 1;

                if ($user->login_tentativi >= self::MAX_TENTATIVI) {
                    $user->login_bloccato_fino = Carbon::now()->addMinutes(self::BLOCCO_MINUTI);
                    $user->login_tentativi     = 0;
                    $user->save();

                    return response()->json([
                        'message' => 'Hai esaurito i tentativi disponibili. Account bloccato per '
                                   . self::BLOCCO_MINUTI . ' minuti.',
                    ], 423);
                }

                $user->save();
                $rimasti = self::MAX_TENTATIVI - $user->login_tentativi;
                return response()->json([
                    'message'           => 'Credenziali non valide. Controlla email e password.',
                    'tentativi_rimasti' => $rimasti,
                ], 401);
            }

            return response()->json([
                'message' => 'Credenziali non valide. Controlla email e password.',
            ], 401);
        }

        // 3. Account disattivato dall'admin?
        if (! $user->attivo) {
            return response()->json([
                'message' => 'Il tuo account e\' stato disattivato. Contatta un amministratore.',
            ], 403);
        }

        // 4. Login OK -> azzera contatore + nuovo token.
        $user->login_tentativi     = 0;
        $user->login_bloccato_fino = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id_utente' => $user->id_utente,
                'nome'      => $user->nome,
                'cognome'   => $user->cognome,
                'email'     => $user->email,
                'tipo'      => $user->tipo_account,
                'ruolo'     => $user->ruolo,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout effettuato con successo',
        ]);
    }
}
