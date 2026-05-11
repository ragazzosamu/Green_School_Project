<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scuola_consumo_mensile extends Model
{
    use HasFactory;

    protected $table      = 'scuola_consumo_mensile';
    protected $primaryKey = 'id_consumo';
    public    $incrementing = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'id_scuola',
        'anno',
        'mese',
        'consumo_elettrico_kwh',
        'consumo_termico_kwh',
        'produzione_fv_kwh',
        'co2_emessa_kg',
    ];

    protected $casts = [
        'anno' => 'integer',
        'mese' => 'integer',
        'consumo_elettrico_kwh' => 'decimal:2',
        'consumo_termico_kwh'   => 'decimal:2',
        'produzione_fv_kwh'     => 'decimal:2',
        'co2_emessa_kg'         => 'decimal:2',
    ];

    public function scuola(): BelongsTo
    {
        return $this->belongsTo(Scuola_profilo::class, 'id_scuola', 'id_scuola');
    }
}
