<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Utenti;

class WebAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Recuperiamo l'utente loggato
            /** @var \App\Models\Utenti $user */
            $user = Auth::user();

            // CREAZIONE TOKEN NEL DB (Tabella personal_access_tokens)
            $user->tokens()->delete(); // Pulizia vecchi token
            $token = $user->createToken('web-access')->plainTextToken;
            
            // Salviamo il token in sessione per la mappa
            session(['api_token' => $token]);

            return redirect()->intended('map');
        }

        throw ValidationException::withMessages([
            'email' => ['Le credenziali inserite non sono corrette.'],
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\Utenti $user */
        $user = Auth::user();
        if ($user) { $user->tokens()->delete(); }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}