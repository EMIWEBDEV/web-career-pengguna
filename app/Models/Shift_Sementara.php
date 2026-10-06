<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift_Sementara extends Model
{
    use HasFactory;
    protected $table = 'HRIS_Shift_Sementara';
    protected $primaryKey = 'Urut';
}
