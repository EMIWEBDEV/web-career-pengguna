<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LemburDetail extends Model
{
     use HasFactory;
    protected $table = 'Transaksi_Lembur_Detail';
    protected $primaryKey = 'Urut_Oto';
    protected $guarded =[];
    public $timestamps = false;

}
