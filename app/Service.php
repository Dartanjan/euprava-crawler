<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'vreme';

    protected $fillable = [
        'id_usluge',
        'id_institucije',
        'naziv',
        'e_usluga',
        'dokument',
        'privreda',
        'zakazivanje',
        'vreme'
    ];

    public function url()
    {
        return "https://www.euprava.gov.rs/eusluge/opis_usluge?generatedServiceId=". $this->id_usluge;
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'id_institucije', 'id_institucije');
    }
}
