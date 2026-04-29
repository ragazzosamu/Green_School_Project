<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utenti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Cerchiamo l'utente tramite email
        $user = Utenti::where('email', $request->email)->first();

        // Se l'utente non esiste o la password è sbagliata
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenziali non valide. Controlla email e password.'
            ], 401);
        }

        // Creiamo il token Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'nome' => $user->nome,
                'cognome' => $user->cognome,
                'email' => $user->email,
                'tipo' => $user->tipo_account
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Cancella il token attuale
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout effettuato con successo'
        ]);
    }
}