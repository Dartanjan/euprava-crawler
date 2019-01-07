<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Crawl extends Model
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
}
