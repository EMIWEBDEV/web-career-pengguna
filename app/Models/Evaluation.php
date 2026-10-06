<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;
    protected $table = 'KPI_Evaluations';
    protected $primaryKey = 'Id_Evaluation';

    public function cases(): BelongsTo
    {
        return $this->belongsTo(Cases::class, 'Case_Id', 'Id_Case');
    }
}
