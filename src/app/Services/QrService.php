<?php

namespace App\Services;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrService
{
    private string $qrKey;
    private string $qrUrl;

    public function __construct()
    {
        $this->qrKey = config('services.qr.secret');
        $this->qrUrl = config('services.qr.domain');
    }

    /**
     * Crea un URL firmato con HMAC-SHA256 per una stazione di ricarica.
     * La firma impedisce che l'URL venga falsificato senza la chiave segreta.
     */
    public function CreaUrlFirmato(string $idStazione) : string
    {
        $firma = hash_hmac('sha256',$idStazione,$this->qrKey);
         return "gs:{$idStazione}:{$firma}";
    }

    /**
     * Verifica che la firma nell'URL corrisponda a quella attesa.
     * Usa hash_equals per prevenire i timing attack.
     */
    public function VerificaFirma(string $idStazione, string $firma): bool
    {
        $firma_attesa = hash_hmac('sha256', $idStazione, $this->qrKey);
        return hash_equals($firma_attesa, $firma);
    }

    /**
     * Genera il QR code SVG a partire dall'URL firmato e lo restituisce come stringa.
     * Il controller si occuperà di salvarlo su file.
     */
    public function GeneraQr(string $idStazione): string
    {
        $url = $this->CreaUrlFirmato($idStazione);

        return (string) QrCode::size(300)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);
    }
}