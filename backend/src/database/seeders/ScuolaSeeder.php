<?php

namespace Database\Seeders;

use App\Models\Scuola_consumo_mensile;
use App\Models\Scuola_profilo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ScuolaSeeder extends Seeder
{
    /**
     * Profilo dell'ITIS di riferimento per Green School + 12 mesi
     * di consumi/produzione con stagionalità realistica per il Veneto.
     */
    public function run(): void
    {
        $scuola = Scuola_profilo::updateOrCreate(
            ['denominazione' => 'ITIS Green School Treviso'],
            [
                'anno_costruzione'  => 1978,
                'superficie_mq'     => 5200.00,
                'classe_energetica' => 'D',
                'interventi_efficientamento' => [
                    'cappotto_termico'     => false,
                    'sostituzione_infissi' => true,
                    'led_relamping'        => true,
                    'caldaia_condensazione'=> true,
                    'pompa_calore'         => false,
                ],
                'fotovoltaico_kwp' => 30.00,
                'descrizione'      => 'Istituto tecnico industriale aderente al progetto Green School. '
                                    . 'Dotato di impianto fotovoltaico da 30 kWp installato nel 2019 '
                                    . 'e stazione di ricarica per e-bike/monopattini elettrici alimentata '
                                    . 'prioritariamente dal surplus solare diurno.',
            ]
        );

        // ── Curve stagionali per il Veneto (vedi calcoli giustificativi) ──
        // Index 0 = gennaio, 11 = dicembre
        $elettricoMensile = [
            7800, 7200, 6800, 5800, 5200, 4500,
            2500, 1800, 5500, 6500, 7500, 7900,
        ]; // kWh

        $termicoMensile = [
            35000, 32000, 22000, 8000, 2000, 0,
                0,     0,     0, 6000, 22000, 36000,
        ]; // kWh

        $fvMensile = [
            1155, 1650, 2805, 3630, 4290, 4785,
            4620, 4125, 2970, 1980, 1155,  825,
        ]; // kWh — campana su giugno, ~33 MWh annui

        // Fattori di emissione medi italiani
        $kgCo2PerKwhElettrico = 0.31; // mix elettrico nazionale
        $kgCo2PerKwhTermico   = 0.20; // gas naturale

        // 12 mesi a ritroso da oggi
        $cursor = Carbon::now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $mese = $cursor->month;
            $anno = $cursor->year;

            $idxStagione = $mese - 1; // mese 1-12 → array 0-11
            $el = $elettricoMensile[$idxStagione];
            $te = $termicoMensile[$idxStagione];
            $fv = $fvMensile[$idxStagione];

            // Piccola variazione casuale ±5% per non avere dati troppo "puliti"
            $el = round($el * mt_rand(95, 105) / 100, 2);
            $te = round($te * mt_rand(95, 105) / 100, 2);
            $fv = round($fv * mt_rand(90, 110) / 100, 2);

            $co2 = max(0, ($el - $fv) * $kgCo2PerKwhElettrico + $te * $kgCo2PerKwhTermico);

            Scuola_consumo_mensile::updateOrCreate(
                ['id_scuola' => $scuola->id_scuola, 'anno' => $anno, 'mese' => $mese],
                [
                    'consumo_elettrico_kwh' => $el,
                    'consumo_termico_kwh'   => $te,
                    'produzione_fv_kwh'     => $fv,
                    'co2_emessa_kg'         => round($co2, 2),
                ]
            );

            $cursor->addMonth();
        }

        $this->command->info('✓ Scuola "' . $scuola->denominazione . '" + 12 mesi di consumi caricati.');
    }
}
