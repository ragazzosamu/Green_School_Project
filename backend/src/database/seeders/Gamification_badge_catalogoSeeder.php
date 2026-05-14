<?php

namespace Database\Seeders;

use App\Models\Gamification_badge_catalogo;
use Illuminate\Database\Seeder;

class Gamification_badge_catalogoSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'codice'      => 'PRIMA_RICARICA',
                'nome'        => 'Prima Ricarica',
                'descrizione' => 'Hai completato la tua prima sessione di ricarica su Green School. Benvenuto a bordo!',
                'icona_emoji' => '⚡',
                'condizione_json' => [
                    'tipo'        => 'conteggio_sessioni',
                    'operatore'   => '>=',
                    'valore'      => 1,
                    'descrizione' => 'Completare almeno 1 sessione di ricarica',
                ],
            ],
            [
                'codice'      => 'ECO_CHAMPION',
                'nome'        => 'Eco Champion',
                'descrizione' => 'Hai risparmiato oltre 50 kg di CO₂ ricaricando il tuo mezzo elettrico. L\'ambiente ringrazia.',
                'icona_emoji' => '🌱',
                'condizione_json' => [
                    'tipo'        => 'soglia_co2',
                    'operatore'   => '>=',
                    'valore'      => 50,
                    'unita'       => 'kg',
                    'descrizione' => 'Risparmiare almeno 50 kg di CO₂ cumulativi',
                ],
            ],
            [
                'codice'      => 'NOTTURNO_GREEN',
                'nome'        => 'Notturno Green',
                'descrizione' => 'Hai effettuato 5 ricariche notturne (22:00–06:00), ottimizzando il carico di rete.',
                'icona_emoji' => '🌙',
                'condizione_json' => [
                    'tipo'        => 'conteggio_sessioni_fascia',
                    'operatore'   => '>=',
                    'valore'      => 5,
                    'fascia_oraria' => ['inizio' => '22:00', 'fine' => '06:00'],
                    'descrizione' => 'Completare 5 ricariche in fascia notturna 22:00–06:00',
                ],
            ],
        ];

        foreach ($badges as $badge) {
            Gamification_badge_catalogo::updateOrCreate(
                ['codice' => $badge['codice']],
                $badge
            );
        }

        $this->command->info('✓ Catalogo badge: ' . count($badges) . ' badge caricati.');
    }
}
