<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'naziv',
        'id_institucije'
    ];

    public function url()
    {
        return "https://www.euprava.gov.rs/eusluge/institucija?service=servicesForInstitution&institutionId=" . $this->id_institucije;
    }

    public function services()
    {
        return $this->hasMany(Crawl::class, 'id_institucije');
    }
}
