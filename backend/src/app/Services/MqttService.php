<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    /**
     * Client persistente usato dal worker per le subscribe + loop.
     * Le publish "una tantum" usano invece un client locale e si disconnettono subito.
     */
    private ?MqttClient $subscriberClient = null;

    /**
     * Pubblica un messaggio (Uso: Controller, Job, oppure dal worker stesso).
     */
    public function publish(string $topic, string $message): void
    {
        $server   = config('services.mqtt.host');
        $port     = (int) config('services.mqtt.port');
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
     * Registra una subscribe sul client persistente.
     * NON avvia il loop: va chiamato loop() una sola volta dopo aver registrato
     * tutte le sottoscrizioni.
     */
    public function subscribe(string $topic, callable $callback): void
    {
        $client = $this->getSubscriberClient();

        $client->subscribe($topic, function ($topic, $message) use ($callback) {
            $callback($topic, $message);
        }, 0);

        $this->infoLog("In ascolto su: $topic");
    }

    /**
     * Avvia il loop di ricezione. Bloccante: va chiamato a fine handle() del worker.
     */
    public function loop(): void
    {
        $this->getSubscriberClient()->loop(true);
    }

    /**
     * Crea (se serve) e restituisce il client condiviso per la subscribe.
     */
    private function getSubscriberClient(): MqttClient
    {
        if ($this->subscriberClient !== null && $this->subscriberClient->isConnected()) {
            return $this->subscriberClient;
        }

        $server   = config('services.mqtt.host');
        $port     = (int) config('services.mqtt.port');
        $clientId = 'laravel_sub_' . uniqid();

        $client = new MqttClient($server, $port, $clientId);

        // Se il tuo broker richiede login, scommenta queste righe:
        /*
        $settings = (new ConnectionSettings)
            ->setUsername(config('services.mqtt.user'))
            ->setPassword(config('services.mqtt.password'))
            ->setKeepAliveInterval(30);
        $client->connect($settings, true);
        */

        $client->connect(null, true);

        $this->subscriberClient = $client;
        $this->infoLog("Connesso al broker $server:$port");

        return $client;
    }

    private function infoLog(string $msg): void
    {
        Log::info("[MQTT] $msg");
    }
}
