<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scuola_profilo extends Model
{
    use HasFactory;

    protected $table      = 'scuola_profilo';
    protected $primaryKey = 'id_scuola';
    public    $incrementing = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'denominazione',
        'anno_costruzione',
        'superficie_mq',
        'classe_energetica',
        'interventi_efficientamento',
        'fotovoltaico_kwp',
        'descrizione',
    ];

    protected $casts = [
        'interventi_efficientamento' => 'array',
        'superficie_mq'    => 'decimal:2',
        'fotovoltaico_kwp' => 'decimal:2',
    ];

    public function consumiMensili(): HasMany
    {
        return $this->hasMany(Scuola_consumo_mensile::class, 'id_scuola', 'id_scuola');
    }
}
