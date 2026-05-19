<?php

namespace Database\Seeders;

use App\Models\Gamification_badge_catalogo;
use Illuminate\Database\Seeder;

class Gamification_badge_catalogoSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // ── Numero di sessioni completate ────────────────────────────────
            [
                'codice'      => 'PRIMA_RICARICA',
                'nome'        => 'Prima Ricarica',
                'descrizione' => 'Hai completato la tua prima sessione di ricarica. Benvenuto a bordo!',
                'icona_emoji' => '⚡',
                'condizione_json' => [
                    'tipo'        => 'conteggio_sessioni',
                    'operatore'   => '>=',
                    'valore'      => 1,
                    'descrizione' => 'Completa almeno 1 sessione di ricarica',
                ],
            ],
            [
                'codice'      => 'VETERANO',
                'nome'        => 'Veterano',
                'descrizione' => 'Hai completato 10 sessioni. Il green school ti conosce ormai.',
                'icona_emoji' => '🏅',
                'condizione_json' => [
                    'tipo'        => 'conteggio_sessioni',
                    'operatore'   => '>=',
                    'valore'      => 10,
                    'descrizione' => 'Completa 10 sessioni di ricarica',
                ],
            ],

            // ── kWh totali ricaricati ────────────────────────────────────────
            [
                'codice'      => 'PRIMI_KWH',
                'nome'        => 'Primi kWh',
                'descrizione' => 'Hai ricaricato un totale di 10 kWh.',
                'icona_emoji' => '🔋',
                'condizione_json' => [
                    'tipo'        => 'soglia_kwh_totali',
                    'operatore'   => '>=',
                    'valore'      => 10,
                    'unita'       => 'kWh',
                    'descrizione' => 'Raggiungi 10 kWh ricaricati in totale',
                ],
            ],
            [
                'codice'      => 'CENTOKWH',
                'nome'        => 'Cento kWh',
                'descrizione' => 'Hai ricaricato 100 kWh complessivi. Energia pura.',
                'icona_emoji' => '🔥',
                'condizione_json' => [
                    'tipo'        => 'soglia_kwh_totali',
                    'operatore'   => '>=',
                    'valore'      => 100,
                    'unita'       => 'kWh',
                    'descrizione' => 'Raggiungi 100 kWh ricaricati in totale',
                ],
            ],

            // ── CO₂ risparmiata ──────────────────────────────────────────────
            [
                'codice'      => 'ECO_BRONZE',
                'nome'        => 'Eco Bronze',
                'descrizione' => 'Hai risparmiato 10 kg di CO₂ rispetto a un mezzo termico.',
                'icona_emoji' => '🌿',
                'condizione_json' => [
                    'tipo'        => 'soglia_co2',
                    'operatore'   => '>=',
                    'valore'      => 10,
                    'unita'       => 'kg',
                    'descrizione' => 'Risparmia 10 kg di CO₂',
                ],
            ],
            [
                'codice'      => 'ECO_CHAMPION',
                'nome'        => 'Eco Champion',
                'descrizione' => 'Hai risparmiato 50 kg di CO₂. L\'ambiente ringrazia.',
                'icona_emoji' => '🌱',
                'condizione_json' => [
                    'tipo'        => 'soglia_co2',
                    'operatore'   => '>=',
                    'valore'      => 50,
                    'unita'       => 'kg',
                    'descrizione' => 'Risparmia 50 kg di CO₂',
                ],
            ],

            // ── Costanza ─────────────────────────────────────────────────────
            [
                'codice'      => 'SETTIMANA_GREEN',
                'nome'        => 'Settimana Green',
                'descrizione' => 'Hai ricaricato per 7 giorni di fila. La routine green funziona.',
                'icona_emoji' => '📅',
                'condizione_json' => [
                    'tipo'        => 'streak_giorni',
                    'operatore'   => '>=',
                    'valore'      => 7,
                    'descrizione' => 'Mantieni uno streak di 7 giorni',
                ],
            ],

            // ── Livello ─────────────────────────────────────────────────────
            [
                'codice'      => 'LIVELLO_5',
                'nome'        => 'Energico',
                'descrizione' => 'Hai raggiunto il livello 5. Le ricariche cominciano a contare davvero.',
                'icona_emoji' => '⭐',
                'condizione_json' => [
                    'tipo'        => 'livello_minimo',
                    'operatore'   => '>=',
                    'valore'      => 5,
                    'descrizione' => 'Raggiungi il livello 5',
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
