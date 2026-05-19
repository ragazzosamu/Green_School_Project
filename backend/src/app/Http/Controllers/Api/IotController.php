<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint per le colonnine IoT (simulatore Python / ESP32).
 *
 * Flusso di registrazione:
 *  - POST /api/iot/registra   pubblico, autenticato con password globale.
 *    Body: { mac, password, numero_punti }. Se MAC sconosciuto crea un
 *    record stazione in stato_setup='in_setup' e altrettanti punti_ricarica
 *    con id_punto = "1".."N". Ritorna l'host del broker MQTT e i nomi dei
 *    topic da usare. Idempotente: chiamate successive ritornano il payload
 *    senza ricreare nulla.
 *
 *    L'admin completa nome/coordinate/metadati dei punti dal pannello e
 *    quando "attiva" la stazione viene pubblicato MQTT stazione/{mac}/ready:
 *    solo da quel momento la colonnina inizia a generare codici monouso e
 *    heartbeat sui topic MQTT.
 */
class IotController extends Controller
{
    public function Registra(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mac'           => ['required', 'string', 'max:32'],
            'password'      => ['required', 'string'],
            'numero_punti'  => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $passwordAttesa = (string) config('services.iot.registration_password');
        if ($passwordAttesa === '' || ! hash_equals($passwordAttesa, $data['password'])) {
            return response()->json(['error' => 'Password registrazione non valida'], 401);
        }

        $mac = strtoupper($data['mac']);
        $numeroPunti = (int) $data['numero_punti'];

        $stazione = DB::table('stazioni')->where('id_stazione', $mac)->first();

        if ($stazione === null) {
            // Prima registrazione: creo stazione + N record punti_ricarica con
            // id_punto = "1".."N". I metadati di ogni punto (tipo veicolo,
            // connettore, potenza) li completera' l'admin dal pannello.
            DB::transaction(function () use ($mac, $numeroPunti) {
                DB::table('stazioni')->insert([
                    'id_stazione'     => $mac,
                    'stato_setup'     => 'in_setup',
                    'libera'          => 1,
                    'in_manutenzione' => false,
                    'tipo_area'       => 'pubblico',
                ]);

                for ($i = 1; $i <= $numeroPunti; $i++) {
                    DB::table('punti_ricarica')->insert([
                        'id_stazione'    => $mac,
                        'id_punto'       => (string) $i,
                        'tipo_veicolo'   => 'auto', // placeholder, l'admin lo cambia in setup
                        'stato_hardware' => 'offline',
                        'libera'         => 1,
                    ]);
                }
            });

            Log::info('[IoT] Nuova colonnina registrata in attesa di setup', [
                'mac'          => $mac,
                'numero_punti' => $numeroPunti,
            ]);
        } else {
            // Verifica coerenza: se l'hardware dichiara un numero di punti
            // diverso da quello a DB, segnalo nei log ma non rifaccio i record
            // (la stazione potrebbe gia' avere sessioni storiche o setup in corso).
            $puntiAttuali = DB::table('punti_ricarica')->where('id_stazione', $mac)->count();
            if ($puntiAttuali !== $numeroPunti) {
                Log::warning('[IoT] Numero punti dichiarato dalla colonnina diverso da quello a DB', [
                    'mac'              => $mac,
                    'numero_dichiarato'=> $numeroPunti,
                    'numero_su_db'     => $puntiAttuali,
                ]);
            }
        }

        $puntiAttuali = DB::table('punti_ricarica')
            ->where('id_stazione', $mac)
            ->orderBy('id_punto')
            ->pluck('id_punto')
            ->all();

        $statoSetup = $stazione->stato_setup ?? 'in_setup';

        return response()->json([
            'id_stazione'      => $mac,
            'stato_setup'      => $statoSetup,
            'id_punti'         => $puntiAttuali,
            'mqtt' => [
                'host' => config('services.mqtt.host'),
                'port' => (int) config('services.mqtt.port'),
            ],
            'topic_ready'             => "stazione/{$mac}/ready",
            'topic_codice'            => "stazione/{$mac}/codice",         // station-wide
            'topic_comandi_stazione'  => "stazione/{$mac}/comandi",        // station-wide (autenticazione_completata)
            'topic_heartbeat'         => "stazione/{$mac}/{id_punto}/heartbeat",
            'topic_eventi'            => "stazione/{$mac}/{id_punto}/eventi",
            'topic_telemetria'        => "stazione/{$mac}/{id_punto}/telemetria",
            'topic_comandi'           => "stazione/{$mac}/{id_punto}/comandi", // per-punto (START/STOP)
        ], 201);
    }
}
