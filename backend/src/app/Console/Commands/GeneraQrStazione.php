<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\QrService;
use Illuminate\Support\Facades\Storage;

#[Signature('app:genera {idStazione}')]
#[Description('Genera il QR code SVG per una stazione di ricarica')]
class GeneraQrStazione extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(QrService $qrService)
    {
        $idStazione = $this->argument('idStazione');
        $svg = $qrService->GeneraQr($idStazione);
        Storage::put('public/qrcodes/' . $idStazione . '.svg', $svg);
        $this->info('QR generato per la stazione ' . $idStazione);
    }
}
