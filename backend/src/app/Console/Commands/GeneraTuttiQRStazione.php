<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\QrService;
use Illuminate\Support\Facades\Storage;

use App\Models\Stazioni;

#[Signature('app:genera-tutti')]
#[Description('Genera i QR code SVG per tutte le stazioni di ricarica')]
class GeneraTuttiQRStazione extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(QrService $qrService)
    {
        $stazioni = Stazioni::all();
        foreach ($stazioni as $stazione) {
            $idStazione = $stazione->id_stazione;
            $svg = $qrService->GeneraQr($idStazione);
            Storage::put('public/qrcodes/' . $idStazione . '.svg', $svg);
            $this->info('QR generato per la stazione ' . $idStazione);
        }
    }
}
