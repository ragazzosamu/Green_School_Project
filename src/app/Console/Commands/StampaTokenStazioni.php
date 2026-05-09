<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('app:stampa-token-stazioni')]
#[Description('Scrive in un file .txt tutti i token in modo da poterli scrivere nelle stazioni.
 Serve perchè in fase di Test i token cambiano continuamente perchè vengono generati casualmenete')]
class StampaTokenStazioni extends Command
{
    /**
     * 
     */
    public function handle()
    {
        //Questo comando andrà eliminato in produzione
        $stazioni = DB::table('stazioni')
        ->select('id_stazione,token')
        ->get();

         // Costruisci il contenuto del file
        $lines = [];
        $lines[] = '═══════════════════════════════════════════════════════════════';
        $lines[] = '  GREEN SCHOOL — DEVICE TOKEN DUMP';
        $lines[] = '  Generato: ' . now()->format('d/m/Y H:i:s');
        $lines[] = '  ⚠  FILE RISERVATO — ';
        $lines[] = '═══════════════════════════════════════════════════════════════';
        $lines[] = '';

        foreach ($stazioni as $stazione) {
            $lines[] = "Codice:     {$stazione->id_stazione}";
            $lines[] = "Token:      {$stazione->token}";
            $lines[] = '───────────────────────────────────────────────────────────────';
        }

        $content = implode(PHP_EOL, $lines);

        // Salva in storage/app/<filename>
        $filename = $this->option('output');
        Storage::disk('local')->put($filename, $content);
        $fullPath = Storage::disk('local')->path($filename);

        $this->newLine();
        $this->warn('⚠  Ricordati di:');
        $this->line('   - Eliminarlo dopo aver flashato le colonnine');
        $this->line('   - Non condividere il file via email/chat');

        return self::SUCCESS;

    }
}
