<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Utenti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            "nome"      => ["required", "string", "max:100"],
            "cognome"   => ["required", "string", "max:100"],
            "email"     => ["required", "email", "max:255", "unique:utenti,email"],
            "cellulare" => ["nullable", "string", "max:20"],
            "password"  => ["required", "confirmed", "min:8"],
        ], [
            "email.unique"       => "Questa email è già registrata.",
            "password.confirmed" => "Le password non coincidono.",
            "password.min"       => "La password deve essere di almeno 8 caratteri.",
        ]);
        $utente = Utenti::create([
            "id_utente"    => Str::uuid()->toString(),
            "nome"         => $request->nome,
            "cognome"      => $request->cognome,
            "email"        => $request->email,
            "cellulare"    => $request->cellulare,
            "tipo_account" => "completo",
            "password"     => Hash::make($request->password),
            "attivo"       => 1,
        ]);
        $token = $utente->createToken("auth_token")->plainTextToken;
        return response()->json([
            "access_token" => $token,
            "token_type"   => "Bearer",
            "user"         => [
                "id_utente" => $utente->id_utente,
                "nome"      => $utente->nome,
                "cognome"   => $utente->cognome,
                "email"     => $utente->email,
                "tipo"      => $utente->tipo_account,
                "ruolo"     => $utente->ruolo,
            ],
        ], 201);
    }
}
