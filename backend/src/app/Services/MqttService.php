<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    /**
     * Pubblica un messaggio (Uso: Controller o Job)
     */
    public function publish(string $topic, string $message)
    {
        $server   = config('services.mqtt.host');
        $port     = config('services.mqtt.port');
        $clientId = 'laravel_pub_' . uniqid();

        $mqtt = new MqttClient($server, $port, $clientId);

        // Se il tuo broker richiede login, scommenta queste righe:
        /*
        $settings = (new ConnectionSettings)
            ->setUsername(config('services.mqtt.user'))
            ->setPassword(config('services.mqtt.password'));
        $mqtt->connect($settings);
        */

        $mqtt->connect();
        $mqtt->publish($topic, $message, 0);
        $mqtt->disconnect();
    }

    /**
     * Ascolta un topic (Uso: Artisan Command)
     * Aggiunto il parametro callable $callback
     */
    public function subscribe(string $topic, callable $callback)
    {
        $server   = config('services.mqtt.host');
        $port     = (int) config('services.mqtt.port'); // Assicurati sia un intero
        $clientId = 'laravel_sub_' . uniqid();

        $mqtt = new MqttClient($server, $port, $clientId);

        $mqtt->connect();
        
        $this->infoLog("Connesso al broker, in ascolto su: $topic");

        $mqtt->subscribe($topic, function ($topic, $message) use ($callback) {
            // Chiamiamo la funzione passata dal Command
            $callback($topic, $message);
        }, 0);
    }

    private function infoLog($msg) 
    {
        // Utile per il debug
        Log::info("[MQTT] $msg");
    }
}