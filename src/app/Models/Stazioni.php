<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute; 
use Illuminate\Support\Facades\DB;               

class Stazioni extends Model
{
    use HasFactory;

    protected $table = 'stazioni'; //tabella che raggruppa le varie aree della scuola dove ci sono colonnine (esempio parcheggio sud o laboratorio)
    protected $primaryKey = 'id_stazione'; //il codice univoco della stazione intera
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
    'id_stazione', 
    'nome', 
    'indirizzo', 
    'latitudine', 
    'longitudine', 
    'coordinata', // <--- Deve esserci questo!
    'tipo_area', 
    'data_attivazione'
]; //info generali sulla stazione: il nome della zona e le coordinate geografiche per trovarla sulla mappa


    protected function coordinata(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // 1. Se il campo nel database è vuoto, restituisci null ed evita errori
                if (is_null($value)) return null;

                try {
                    /**
                     * 2. Il dato nel DB è in formato BINARIO (illeggibile per JSON).
                     * Usiamo la funzione SQL 'ST_AsText' per convertirlo in una stringa leggibile.
                     * Esempio di trasformazione: [01010000...] -> "POINT(45.123 9.456)"
                     */
                    $res = DB::select("SELECT ST_AsText(?) AS wkt", [$value]);

                    // 3. Restituisci la stringa convertita o null se la conversione fallisce
                    return $res[0]->wkt ?? null;
                    
                } catch (\Exception $e) {
                    // In caso di errore durante la conversione, restituisci un messaggio di debug
                    return "Errore conversione dati geografici";
                }
            }
        );
    }

    /**
    * Calcola lo stato aggregato della stazione a cui appartiene un punto di ricarica.
     *
     * Esegue due query: la prima recupera l'id_stazione dal punto specificato,
     * la seconda raggruppa tutti i punti della stessa stazione e calcola i contatori
     * (totali, occupati, liberi) usando un GROUP BY con SUM condizionali.
     *
     * Utile per determinare se un cambio di stato sul singolo punto comporta
     * anche un cambio di stato della stazione nel suo complesso (es. ultimo punto
     * libero che si occupa → stazione piena, oppure primo punto che si libera
     * dopo essere stata piena → stazione di nuovo disponibile).
     *
     * @param string $idPunto UUID del punto di ricarica di riferimento
     * @return object|null Oggetto con campi {id_stazione, totali, occupati, liberi},
     *                     oppure null se il punto non esiste nel database
     */
    
    public static function statoAggregatoPerPunto(string $idPunto): ?object
    {
        $idStazione = DB::table('punti_ricarica')
            ->where('id_punto', $idPunto)
            ->value('id_stazione');

        if (!$idStazione) return null;

        return DB::table('punti_ricarica')
            ->where('id_stazione', $idStazione)
            ->groupBy('id_stazione')
            ->selectRaw("
                id_stazione,
                COUNT(*) as totali,
                SUM(CASE WHEN libera = 0 THEN 1 ELSE 0 END) as occupati,
                SUM(CASE WHEN libera = 1 AND stato_hardware = 'operativo' THEN 1 ELSE 0 END) as liberi
            ")
            ->first();
    }

    public function puntiRicarica() {
        return $this->hasMany(Punti_ricarica::class, 'id_stazione', 'id_stazione'); //permette di vedere quante e quali colonnine sono montate dentro questa specifica stazione
    }
    public function accumulatori() {
        return $this->hasMany(Accumulatori_stazione::class, 'id_stazione', 'id_stazione'); //serve a vedere quali batterie di accumulo sono instalate in questa zona per gestire l energia
    }
}