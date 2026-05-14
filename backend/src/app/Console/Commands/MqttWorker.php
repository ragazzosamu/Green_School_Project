<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Punti_ricarica;
use App\Events\TelemetriaRicevuta;

#[Signature('mqtt:leggi')]
#[Description('Funziona in background e ascolta se ci sono aggiornamenti sui topic mqtt')]
class MqttWorker extends Command
{
    protected $mqttService;

    public function __construct(MqttService $mqttService)
    {
        parent::__construct();
        $this->mqttService = $mqttService;
    }

    public function handle()
    {
        $this->mqttService->subscribe('stazione/+/telemetria', function ($topic, $message) 
        {
            $id_punto = explode('/', $topic)[1];
            $this->info("-> Telemetria ricevuta da stazione $id_punto");
            
            $data = json_decode($message, true);

            // 2. Controllo sicurezza: il JSON è valido?
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Errore: Ricevuto messaggio non JSON su $topic");
                return;
            }

            TelemetriaRicevuta::dispatch($data['id_punto'], $data['delta_kwh'], $data['id_sessione']);
        });

        // 2. Canale Eventi
        $this->mqttService->subscribe('stazione/+/eventi', function ($topic, $message) {
            $id_punto = explode('/', $topic)[1];
            $this->info("Evento da stazione $id_punto: $message");
            
            #necessario scrivere prima interrmpi sessione

            # collega cavo

            
        });

        // 3. Canale Heartbeat
        $this->mqttService->subscribe('stazione/+/heartbeat', function ($topic, $message) {
            $id_punto = explode('/', $topic)[1];
            $this->comment("♥ Heartbeat stazione $id_punto");
            
            $data = json_decode($message, true);

            // 2. Controllo sicurezza: il JSON è valido?
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Errore: Ricevuto messaggio non JSON su $topic");
                return;
            }

            Punti_ricarica::where('id_punto', $data['id_punto'])
            ->update([
                'data_ultimo_heartbeat' => $data['ts'] ?? null,
            ]);

        });

        // AVVISO: Il loop deve essere chiamato una sola volta alla fine!
        $this->mqttService->startLoop();
    }
    
}
